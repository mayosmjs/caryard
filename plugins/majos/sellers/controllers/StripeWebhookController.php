<?php

namespace Majos\Sellers\Controllers;

use Illuminate\Http\Request;
use Majos\Sellers\Classes\SubscriptionService;
use Majos\Sellers\Classes\Payments\StripeProvider;
use Majos\Sellers\Models\SubscriptionTransaction;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController
{
    /**
     * Handle Stripe webhook notification
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // Use Stripe's official SDK for signature verification
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        // Skip verification if secret is not configured (for development)
        if (!$secret) {
            \Log::debug('Stripe webhook: No webhook secret configured');
        } else {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (SignatureVerificationException $e) {
                \Log::warning('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
            } catch (\UnexpectedValueException $e) {
                \Log::warning('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Invalid payload'], 400);
            } catch (\Exception $e) {
                \Log::warning('Stripe webhook: Verification failed', ['error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Webhook verification failed'], 400);
            }
        }
        
        try {
            // Use the verified event object from Stripe SDK
            // If verification was skipped, fall back to parsing raw payload
            if (isset($event)) {
                $eventType = $event->type;
                $eventData = $event->data->object ?? [];
                $stripeEventId = $event->id ?? null;
                $apiVersion = $event->api_version ?? null;
                $rawPayload = $payload;
            } else {
                // Fallback: parse payload manually (less secure, only used when secret not configured)
                $eventDataArray = json_decode($payload, true) ?: $request->all();
                $eventType = $eventDataArray['type'] ?? '';
                $eventData = $eventDataArray['data']['object'] ?? [];
                $stripeEventId = $eventDataArray['id'] ?? null;
                $apiVersion = $eventDataArray['api_version'] ?? null;
                $rawPayload = $payload;
            }
            
            // Log raw webhook
            \Log::info('Stripe Webhook Received', [
                'type' => $eventType,
                'stripe_event_id' => $stripeEventId,
                'has_signature' => !!$sigHeader
            ]);
            
            // Get the payment intent ID
            $paymentIntentId = $eventData['id'] ?? '';
            
            if (!$paymentIntentId) {
                return response()->json(['success' => false, 'message' => 'No payment intent ID'], 400);
            }
            
            // Find transaction using helper method
            $transaction = SubscriptionTransaction::findByPaymentIntentId($paymentIntentId);
            
            if (!$transaction) {
                \Log::warning('Stripe webhook: Transaction not found for ' . $paymentIntentId);
                return response()->json(['success' => true, 'message' => 'Transaction not found']);
            }
            
            if ($transaction->status === 'completed') {
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }
            
            // Store webhook data for ALL events - capture complete response
            $metadata = $transaction->metadata ?? [];
            
            // Build comprehensive webhook data
            $webhookData = [
                'event_type' => $eventType,
                'received_at' => now()->toIso8601String(),
                'stripe_event_id' => $stripeEventId,
                'api_version' => $apiVersion,
                'payment_intent_id' => $paymentIntentId,
                // Payment Intent details
                'amount' => $eventData['amount'] ?? null,
                'amount_captured' => $eventData['amount_captured'] ?? null,
                'amount_refunded' => $eventData['amount_refunded'] ?? null,
                'currency' => $eventData['currency'] ?? null,
                'status' => $eventData['status'] ?? null,
                // Customer details
                'customer' => $eventData['customer'] ?? null,
                'customer_email' => $eventData['customer_email'] ?? null,
                // Payment details
                'payment_method' => $eventData['payment_method'] ?? null,
                'payment_method_types' => $eventData['payment_method_types'] ?? null,
                'payment_method_details' => $eventData['payment_method_details'] ?? null,
                // Billing
                'billing_address' => $eventData['billing_details'] ?? null,
                // Card details if available
                'card_brand' => $eventData['payment_method_details']['card']['brand'] ?? null,
                'card_last4' => $eventData['payment_method_details']['card']['last4'] ?? null,
                'card_exp_month' => $eventData['payment_method_details']['card']['exp_month'] ?? null,
                'card_exp_year' => $eventData['payment_method_details']['card']['exp_year'] ?? null,
                // Additional info
                'description' => $eventData['description'] ?? null,
                'receipt_email' => $eventData['receipt_email'] ?? null,
                'statement_descriptor' => $eventData['statement_descriptor'] ?? null,
                'captured' => $eventData['captured'] ?? null,
                'confirmation_method' => $eventData['confirmation_method'] ?? null,
                'invoice' => $eventData['invoice'] ?? null,
                // Error details
                'last_payment_error' => $eventData['last_payment_error'] ?? null,
                // Metadata
                'metadata' => $eventData['metadata'] ?? null,
                // RAW - store complete response
                'raw_payload' => $rawPayload,
                'event_data_object' => $eventData,
            ];
            
            $metadata['stripe_webhook'] = $webhookData;
            
            // Process based on event type
            switch ($eventType) {
                case 'payment_intent.succeeded':
                    $service = new SubscriptionService();
                    $result = $service->completePayment($paymentIntentId, 'stripe', true, [
                        'raw'    => $rawPayload,
                        'status' => 'succeeded',
                    ]);
                    // Add rich payer/card/outcome summary extracted from the PaymentIntent object
                    $stripeHelper = new StripeProvider();
                    $metadata['stripe'] = $stripeHelper->extractStripePaymentSummary($eventData);
                    $metadata['payment_completed_at'] = now()->toIso8601String();
                    \Log::info('Stripe payment completed via webhook', [
                        'transaction_id' => $paymentIntentId,
                        'result'         => $result->success,
                        'customer_email' => $metadata['stripe']['customer_email'] ?? null,
                        'card_last4'     => $metadata['stripe']['card_last4'] ?? null,
                    ]);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $errorMessage = $eventData['last_payment_error']['message'] ?? 'Payment failed';
                    $transaction->status = SubscriptionTransaction::STATUS_FAILED;
                    // Extract full payer/card/decline info from the PaymentIntent object
                    $stripeHelper = new StripeProvider();
                    $metadata['stripe']           = $stripeHelper->extractStripePaymentSummary($eventData);
                    $metadata['failure_reason']   = $errorMessage;
                    $metadata['payment_failed_at'] = now()->toIso8601String();
                    \Log::info('Stripe payment failed via webhook', [
                        'transaction_id' => $paymentIntentId,
                        'error'          => $errorMessage,
                        'failure_code'   => $metadata['stripe']['failure_code']  ?? null,
                        'decline_code'   => $metadata['stripe']['decline_code']  ?? null,
                        'card_last4'     => $metadata['stripe']['card_last4']    ?? null,
                        'customer_email' => $metadata['stripe']['customer_email'] ?? null,
                    ]);
                    break;
                    
                case 'charge.refunded':
                    $metadata['payment_refunded_at'] = now()->toIso8601String();
                    \Log::info('Stripe payment refunded via webhook', ['transaction_id' => $paymentIntentId]);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $metadata['invoice_paid_at'] = now()->toIso8601String();
                    \Log::info('Stripe invoice payment succeeded via webhook', ['transaction_id' => $paymentIntentId]);
                    break;
                    
                default:
                    \Log::info('Stripe webhook received: ' . $eventType, ['transaction_id' => $paymentIntentId]);
            }
            
            $transaction->metadata = $metadata;
            $transaction->save();
            
            return response()->json(['success' => true, 'message' => 'Webhook processed']);
            
        } catch (\Exception $e) {
            \Log::error('Stripe Webhook Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
