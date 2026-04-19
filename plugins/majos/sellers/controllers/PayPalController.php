<?php

namespace Majos\Sellers\Controllers;

use Illuminate\Http\Request;
use Majos\Sellers\Classes\SubscriptionService;
use Majos\Sellers\Classes\Payments\PaymentFactory;
use Majos\Sellers\Models\SubscriptionTransaction;
use Redirect;
use Flash;

class PayPalController
{
    /**
     * Verify PayPal payment via API
     * GET /subscription/paypal/verify?token=...
     */
    public function verify(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $token = $request->get('token');
            
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Missing token'], 400);
            }
            
            // Find transaction
            $transaction = SubscriptionTransaction::where('transaction_id', $token)
                ->where('provider', 'paypal')
                ->first();
                
            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }
            
            // If already completed, return success
            if ($transaction->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment already completed'
                ]);
            }
            
            // Try direct verification with PayPal API
            $paypalProvider = PaymentFactory::makeFromSettings('paypal');
            
            // First try with capture_id if available
            $metadata = $transaction->metadata ?? [];
            $captureId = $metadata['paypal']['capture_id'] ?? null;
            
            if ($captureId) {
                $verifyResult = $paypalProvider->verifyPayment($captureId);
                
                if ($verifyResult->success) {
                    $service = new SubscriptionService();
                    $service->completePayment($token, 'paypal', true, $verifyResult->data);
                    
                    return response()->json([
                        'success' => true,
                        'status' => 'completed',
                        'message' => 'Payment verified and completed'
                    ]);
                }
            }
            
            // Try with order ID as fallback
            $verifyResult = $paypalProvider->verifyPayment($token);
            
            if ($verifyResult->success) {
                $service = new SubscriptionService();
                $service->completePayment($token, 'paypal', true, $verifyResult->data);
                
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment verified and completed'
                ]);
            }
            
            // Check if PayPal says it's pending
            if (!empty($verifyResult->data['is_pending'])) {
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => $verifyResult->message ?? 'Payment is pending approval'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => $verifyResult->message ?? 'Payment verification failed'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('PayPal Verify Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show payment processing page with polling
     * GET /subscription/paypal/processing-page?token=...
     */
    public function processing(Request $request)
    {
        $token = $request->get('token');
        
        if (!$token) {
            Flash::error('Invalid request: missing token');
            return Redirect::to('/account/subscription');
        }
        
        // Render the processing page
        return \Response::view('plugins/majos/sellers::subscription/paypal_processing', [
            'token' => $token,
        ]);
    }

    /**
     * Handle PayPal return (user approved payment)
     * GET /subscription/paypal/return?token=...&PayerID=...
     */
    public function handleReturn(Request $request)
    {
        try {
            \Log::info('PayPal Return Received', $request->all());
            
            $token = $request->get('token');
            $PayerID = $request->get('PayerID');
            
            if (!$token) {
                Flash::error('Invalid PayPal return: missing token');
                return Redirect::to('/account/subscription');
            }
            
            // Find the transaction by order ID
            $existingTransaction = SubscriptionTransaction::where('transaction_id', $token)->first();

            if (!$existingTransaction) {
                \Log::error('PayPal return: Transaction not found for token: ' . $token);
                Flash::error('Transaction not found');
                return Redirect::to('/account/subscription');
            }

            // If already completed, redirect to success
            if ($existingTransaction->status === 'completed') {
                Flash::success('Payment already completed');
                return Redirect::to('/account/subscription');
            }

            // Store PayerID in metadata for reference
            $metadata = $existingTransaction->metadata ?? [];
            $metadata['paypal_return'] = [
                'return_received_at' => now()->toIso8601String(),
                'token' => $token,
                'payer_id' => $PayerID,
            ];
            $existingTransaction->metadata = $metadata;
            $existingTransaction->save();

            // Redirect to processing page that will poll for status
            return Redirect::to('/subscription/paypal/processing-page?token=' . $token);
            
        } catch (\Exception $e) {
            \Log::error('PayPal Return Error: ' . $e->getMessage());
            Flash::error('Payment processing failed');
            return Redirect::to('/account/subscription');
        }
    }

    /**
     * Handle PayPal cancellation (user cancelled payment)
     * GET /subscription/paypal/cancel?token=...
     */
    public function handleCancel(Request $request)
    {
        try {
            $token = $request->get('token');
            
            if ($token) {
                // Find and mark transaction as cancelled/failed
                $transaction = SubscriptionTransaction::where('transaction_id', $token)
                    ->where('provider', 'paypal')
                    ->first();
                    
                if ($transaction && $transaction->status !== 'completed') {
                    $transaction->status = SubscriptionTransaction::STATUS_FAILED;
                    $metadata = $transaction->metadata ?? [];
                    $metadata['failure_reason'] = 'Payment cancelled by user';
                    $transaction->metadata = $metadata;
                    $transaction->save();
                }
            }
            
            Flash::info('Payment was cancelled');
            return redirect('/account/subscription');
            
        } catch (\Exception $e) {
            \Log::error('PayPal Cancel Error: ' . $e->getMessage());
            return redirect('/account/subscription');
        }
    }
}
