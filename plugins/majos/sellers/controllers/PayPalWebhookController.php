<?php

namespace Majos\Sellers\Controllers;

use Illuminate\Http\Request;
use Majos\Sellers\Classes\Payments\PayPalProvider;
use Majos\Sellers\Models\SubscriptionTransaction;

class PayPalWebhookController
{
    /**
     * Handle PayPal webhook notification
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        \Log::info('PayPal Return Webhook xxx', $request->all());

        try {
            $payload = $request->all();
            
            // Get PayPal transmission ID for verification
            // Note: Paypal-Transmission-Id is the unique ID, Paypal-Transmission-Sig is the signature
            $transmissionId = $request->header('Paypal-Transmission-Id') ?? $request->header('X-Paypal-Transmission-Id');
            $timestamp = $request->header('Paypal-Transmission-Time') ?? $request->header('X-Paypal-Transmission-Time');
            $webhookId = $request->header('Paypal-Webhook-Id') ?? $request->header('X-Paypal-Webhook-Id');
            
            \Log::info('PayPal Webhook Received', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'resource_id' => $payload['resource']['id'] ?? '',
                'transmission_id' => $transmissionId ? 'present' : 'missing',
                'webhook_id' => $webhookId ?? 'missing'
            ]);
            
            // Idempotency: Check if this webhook was already processed
            $eventId = $payload['id'] ?? null;
            if ($eventId) {
                $existingEvent = SubscriptionTransaction::where('metadata', 'LIKE', '%"paypal_event_id":"' . $eventId . '"%')->first();
                if ($existingEvent) {
                    \Log::info('PayPal webhook already processed', ['event_id' => $eventId]);
                    return response()->json(['success' => true, 'message' => 'Webhook already processed']);
                }
            }
            
            // Verify webhook signature (in production)
            // Skip verification for sandbox testing, but log it
            $settings = \Majos\Sellers\Models\Settings::instance();
            $paypalConfig = \Majos\Sellers\Classes\Payments\PaymentFactory::getSettingsConfig('paypal');
            $isSandbox = ($paypalConfig['environment'] ?? 'sandbox') === 'sandbox';
            
            if (!$isSandbox && $transmissionId && $webhookId) {
                // Production: verify the signature
                $signatureVerified = $this->verifyPayPalSignature($paypalConfig, $payload, $transmissionId, $timestamp, $webhookId);
                if (!$signatureVerified) {
                    \Log::warning('PayPal webhook signature verification failed', ['event_id' => $eventId]);
                    return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
                }
            } else if (!$isSandbox) {
                \Log::warning('PayPal webhook missing signature headers', ['event_id' => $eventId]);
                return response()->json(['success' => false, 'message' => 'Missing signature'], 401);
            }
            
            $eventType = $payload['event_type'] ?? '';
            $resource = $payload['resource'] ?? [];
            
            // Process based on event type
            switch ($eventType) {
                case 'CHECKOUT.ORDER.APPROVED':
                    $orderId = $resource['id'] ?? '';
                    \Log::info('PayPal order approved', ['order_id' => $orderId]);
                    
                    // Find and update the transaction
                    $transaction = SubscriptionTransaction::where('transaction_id', $orderId)->first();
                    if ($transaction) {
                        $metadata = $transaction->metadata ?? [];
                        $metadata['paypal_event_id'] = $eventId;
                        $metadata['order_approved_at'] = now()->toIso8601String();
                        $transaction->metadata = $metadata;
                        $transaction->save(); 
                    }
                    break;
                    
                case 'PAYMENT.CAPTURE.COMPLETED':
                     \Log::info('PayPal Return Webhoook PAYmento', $request->all());
                    $captureId = $resource['id'] ?? '';
                    $customId = $resource['custom_id'] ?? '';
                    
                    \Log::info('PayPal payment completed', [
                        'capture_id' => $captureId,
                        'custom_id' => $customId,
                        'event_id' => $eventId
                    ]);
                    
                    // Find transaction by capture_id or order ID using helper methods
                    $transaction = null;

                    // 1. Try to find by capture_id stored in metadata (set after return-URL capture)
                    if ($captureId) {
                        $transaction = SubscriptionTransaction::findByCaptureId($captureId);
                    }

                    // 2. Try by custom_id directly (order ID or subscription alias)
                    if (!$transaction && $customId) {
                        // It might be the Order ID
                        $transaction = SubscriptionTransaction::findByOrderId($customId);
                        
                        // Or it might be our 'SUB-{id}' format
                        if (!$transaction && strpos($customId, 'SUB-') === 0) {
                            $transaction = SubscriptionTransaction::findByPayPalCustomId($customId);
                        }
                    }

                    // 3. Try to extract Order ID from links (the 'up' link points to the Order)
                    if (!$transaction && !empty($resource['links'])) {
                        foreach ($resource['links'] as $link) {
                            if (($link['rel'] ?? '') === 'up' && !empty($link['href'])) {
                                // Link is /v2/checkout/orders/{order_id}
                                $parts = explode('/', $link['href']);
                                $extractedOrderId = end($parts);
                                if ($extractedOrderId) {
                                    \Log::info('PayPal webhook: extracted Order ID from links', ['order_id' => $extractedOrderId]);
                                    $transaction = SubscriptionTransaction::findByOrderId($extractedOrderId);
                                    break;
                                }
                            }
                        }
                    }

                    // 4. Try by supplementary_data.related_ids.order_id (as per PayPal response structure)
                    if (!$transaction && !empty($resource['supplementary_data']['related_ids']['order_id'])) {
                        $relatedOrderId = $resource['supplementary_data']['related_ids']['order_id'];
                        \Log::info('PayPal webhook: found order_id in supplementary_data.related_ids', ['order_id' => $relatedOrderId]);
                        $transaction = SubscriptionTransaction::findByOrderId($relatedOrderId);
                    }
                    
                    // 5. Also check supplementary_data.order_id as fallback
                    if (!$transaction && !empty($resource['supplementary_data']['order_id'])) {
                        $transaction = SubscriptionTransaction::findByOrderId($resource['supplementary_data']['order_id']);
                    }

                    \Log::info('PayPal PAYMENT.CAPTURE.COMPLETED - transaction lookup result', [
                        'capture_id'    => $captureId,
                        'custom_id'     => $customId,
                        'found_id'      => $transaction ? $transaction->id : 'NOT FOUND',
                        'found_status'  => $transaction ? $transaction->status : null,
                    ]);
                    
                    if ($transaction && $transaction->status !== 'completed') {
                        // Store event ID for idempotency and full webhook data
                        $metadata = $transaction->metadata ?? [];
                        
                        // Extract rich summary from the webhook resource
                        $paypalHelper = new PayPalProvider();
                        $summary      = $paypalHelper->extractPayPalPaymentSummary(['purchase_units' => [['payments' => ['captures' => [$resource]]]]]);
                        // Note: Simplified payload wrap for the summary helper to parse correctly
                        
                        $metadata['paypal'] = $summary;
                        $metadata['paypal_webhook'] = [
                            'event_type' => $eventType,
                            'event_id' => $eventId,
                            'received_at' => now()->toIso8601String(),
                            'capture_id' => $captureId,
                            'custom_id' => $customId,
                            'status' => $resource['status'] ?? null,
                        ];
                        
                        // Mark as completed
                        $transaction->status = SubscriptionTransaction::STATUS_COMPLETED;
                        $transaction->transaction_id = $captureId ?: $customId;
                        $transaction->metadata = $metadata;
                        $transaction->save();
                        
                        \Log::info('PayPal payment marked as completed', [
                            'transaction_id' => $transaction->id,
                            'capture_id' => $captureId,
                        ]);
                    } else if ($transaction) {
                        \Log::info('PayPal payment already completed', ['transaction_id' => $transaction->id]);
                    } else {
                        \Log::warning('PayPal payment capture: transaction not found', [
                            'capture_id' => $captureId,
                            'custom_id' => $customId,
                            'event_id' => $eventId
                        ]);
                    }
                    break;
                    
                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.REFUNDED':
                case 'PAYMENT.CAPTURE.REVERSED':
                    $captureId = $resource['id'] ?? '';
                    $reason = $resource['reason'] ?? ($eventType === 'PAYMENT.CAPTURE.REFUNDED' ? 'refunded' : 'denied');
                    
                    \Log::info('PayPal payment ' . $reason, ['capture_id' => $captureId]);
                    
                    $transaction = SubscriptionTransaction::findByCaptureId($captureId);
                    if ($transaction && $transaction->status !== 'failed') {
                        $transaction->status = SubscriptionTransaction::STATUS_FAILED;
                        
                        $metadata = $transaction->metadata ?? [];
                        $metadata['failure_reason'] = $reason;
                        $metadata['paypal_webhook'] = [
                            'event_type' => $eventType,
                            'event_id' => $eventId,
                            'received_at' => now()->toIso8601String(),
                            'capture_id' => $captureId,
                        ];
                        $transaction->metadata = $metadata;
                        $transaction->save();
                        
                        \Log::info('PayPal payment marked as failed', ['transaction_id' => $transaction->id]);
                    }
                    break;
                    
                case 'CHECKOUT.ORDER.CANCELLED':
                    $orderId = $resource['id'] ?? '';
                    \Log::info('PayPal order cancelled', ['order_id' => $orderId]);
                    
                    $transaction = SubscriptionTransaction::where('transaction_id', $orderId)->first();
                    if ($transaction && $transaction->status !== 'failed' && $transaction->status !== 'completed') {
                        $transaction->status = SubscriptionTransaction::STATUS_FAILED;
                        
                        $metadata = $transaction->metadata ?? [];
                        $metadata['failure_reason'] = 'Order cancelled by customer';
                        $metadata['paypal_webhook'] = [
                            'event_type' => $eventType,
                            'event_id' => $eventId,
                            'received_at' => now()->toIso8601String(),
                        ];
                        $transaction->metadata = $metadata;
                        $transaction->save();
                    }
                    break;
                    
                default:
                    \Log::info('PayPal webhook: Unhandled event type: ' . $eventType);
            }
            
            return response()->json(['success' => true, 'message' => 'Webhook processed']);
            
        } catch (\Exception $e) {
            \Log::error('PayPal Webhook Error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify PayPal webhook signature
     */
    private function verifyPayPalSignature(array $config, array $payload, string $transmissionId, string $timestamp, string $webhookId): bool
    {
        try {
            $accessToken = $this->getPayPalAccessToken($config);
            if (!$accessToken) {
                \Log::warning('PayPal: Failed to get access token for signature verification');
                return false;
            }
            
            $verificationBody = [
                'auth_algo' => 'SHA256withRSA',
                'transmission_id' => $transmissionId,
                'transmission_sig' => request()->header('Paypal-Transmission-Sig'),
                'transmission_time' => $timestamp,
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ];
            
            $ch = curl_init('https://' . ($config['environment'] === 'sandbox' ? 'api-m.sandbox.paypal.com' : 'api-m.paypal.com') . '/v1/notifications/verify-webhook-signature');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verificationBody));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode === 200 && isset($result['verification_status']) && $result['verification_status'] === 'SUCCESS') {
                \Log::info('PayPal webhook signature verified successfully');
                return true;
            }
            
            \Log::warning('PayPal webhook signature verification failed', [
                'http_code' => $httpCode,
                'response' => $result
            ]);
            return false;
            
        } catch (\Exception $e) {
            \Log::error('PayPal signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get PayPal access token for API calls
     */
    private function getPayPalAccessToken(array $config): ?string
    {
        $url = $config['environment'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api-m.paypal.com/v1/oauth2/token';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
}
