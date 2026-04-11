<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Majos\Sellers\Classes\SubscriptionService;
use Majos\Sellers\Classes\Payments\PaymentFactory;
use Majos\Sellers\Models\SubscriptionTransaction;

/**
 * Verify PayPal webhook signature
 */
function verifyPayPalSignature(array $config, array $payload, string $transmissionId, string $timestamp, string $webhookId): bool
{
    try {
        $accessToken = getPayPalAccessToken($config);
        if (!$accessToken) {
            Log::warning('PayPal: Failed to get access token for signature verification');
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
            Log::info('PayPal webhook signature verified successfully');
            return true;
        }
        
        Log::warning('PayPal webhook signature verification failed', [
            'http_code' => $httpCode,
            'response' => $result
        ]);
        return false;
        
    } catch (\Exception $e) {
        Log::error('PayPal signature verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get PayPal access token for API calls
 */
function getPayPalAccessToken(array $config): ?string
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

Route::post('/api/stripe/webhook', function (Request $request) {
    try {
        $payload = $request->all();
        $sigHeader = $request->header('stripe-signature');
        
        // Log raw webhook
        \Log::info('Stripe Webhook Received', [
            'type' => $payload['type'] ?? 'unknown',
            'has_signature' => !!$sigHeader
        ]);
        
        $eventType = $payload['type'] ?? '';
        $eventData = $payload['data']['object'] ?? [];
        
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
            'stripe_event_id' => $payload['id'] ?? null,
            'api_version' => $payload['api_version'] ?? null,
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
            'raw_payload' => $payload,
            'event_data_object' => $eventData,
        ];
        
        $metadata['stripe_webhook'] = $webhookData;
        
        // Process based on event type
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $service = new SubscriptionService();
                $result = $service->completePayment($paymentIntentId, 'stripe', true, [
                    'raw'    => $payload,
                    'status' => 'succeeded',
                ]);
                // Add rich payer/card/outcome summary extracted from the PaymentIntent object
                $stripeHelper = new \Majos\Sellers\Classes\Payments\StripeProvider();
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
                $stripeHelper = new \Majos\Sellers\Classes\Payments\StripeProvider();
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
});

Route::post('/api/mpesa/callback', function (Request $request) {
    try {
        $data = $request->all();
        
        // Log raw webhook
        \Log::info('M-Pesa Webhook Received', ['data' => $data]);
        
        $resultCode = $data['Body']['stkCallback']['ResultCode'] ?? null;
        $checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'] ?? null;
        
        if (!$checkoutRequestId) {
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 400);
        }
        
        // Use helper method to find transaction
        $transaction = SubscriptionTransaction::findByCheckoutRequestId($checkoutRequestId);
        
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }
        
        if ($transaction->status === 'completed') {
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }
        
        if ($resultCode === 0) {
            // Success
            $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? 0;
            $mpesaReceiptNumber = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? '';
            $phone = $data['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'] ?? '';
            
            // Save receipt in metadata and swap transaction_id
            $metadata = $transaction->metadata ?? [];
            $metadata['mpesa_receipt'] = $mpesaReceiptNumber;
            $metadata['checkout_request_id'] = $checkoutRequestId;
            $metadata['customer_phone'] = $phone;
            $metadata['webhook_payload'] = $data;
            
            $transaction->metadata = $metadata;
            
            if ($mpesaReceiptNumber) {
                $transaction->transaction_id = $mpesaReceiptNumber;
            }
            $transaction->save();
            
            $service = new SubscriptionService();
            $service->completePayment($transaction->transaction_id, 'mpesa', true, [
                'raw' => $data,
                'result_desc' => 'Confirmed via Webhook'
            ]);
            
        } else {
            // Failed
            $desc = $data['Body']['stkCallback']['ResultDesc'] ?? 'Failed';
            $transaction->status = SubscriptionTransaction::STATUS_FAILED;
            
            $metadata = $transaction->metadata ?? [];
            $metadata['failure_reason'] = $desc;
            $metadata['webhook_payload'] = $data;
            $transaction->metadata = $metadata;
            $transaction->save();
        }

        \Log::error('M-Pesa Webhook return: ' . json_encode($transaction));
        return response()->json(['success' => true, 'message' => 'Webhook processed']);

    } catch (\Exception $e) {
        \Log::error('M-Pesa Webhook Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// PayPal Routes
// NEW: Processing page that polls for payment status
Route::get('/subscription/paypal/processing', function (Request $request) {
    try {
        $token = $request->get('token');
        $PayerID = $request->get('PayerID');
        
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Missing token'], 400);
        }
        
        // Find transaction by order ID (token)
        $transaction = SubscriptionTransaction::where('transaction_id', $token)
            ->where('provider', 'paypal')
            ->first();
            
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }
        
        // Check current status
        $status = $transaction->status;
        $metadata = $transaction->metadata ?? [];
        
        if ($status === 'completed') {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'message' => 'Payment completed successfully'
            ]);
        }
        
        // Check if we have capture_id in metadata (webhook may have processed it)
        if (!empty($metadata['paypal']['capture_id'])) {
            // Try to verify directly with PayPal API
            $paypalProvider = \Majos\Sellers\Classes\Payments\PaymentFactory::makeFromSettings('paypal');
            $verifyResult = $paypalProvider->verifyPayment($metadata['paypal']['capture_id']);
            
            if ($verifyResult->success) {
                // Complete the payment
                $service = new SubscriptionService();
                $service->completePayment($token, 'paypal', true, $verifyResult->data);
                
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment completed successfully'
                ]);
            }
        }
        
        // Still pending - return pending status
        return response()->json([
            'success' => true,
            'status' => 'pending',
            'message' => 'Payment is being processed'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('PayPal Processing Check Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// NEW: Direct verification endpoint with PayPal API fallback
Route::get('/subscription/paypal/verify', function (Request $request) {
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
        $paypalProvider = \Majos\Sellers\Classes\Payments\PaymentFactory::makeFromSettings('paypal');
        
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
});

// Processing page with polling - shows "Processing" state
Route::get('/subscription/paypal/processing-page', function (Request $request) {
    $token = $request->get('token');
    
    if (!$token) {
        \Flash::error('Invalid request: missing token');
        return Redirect::to('/account/subscription');
    }
    
    // Render a simple processing page
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processing Payment...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .pulse-amber {
            animation: pulse-amber 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse-amber {
            0%, 100% { opacity: 1; }
            50% { opacity: .7; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl p-10 max-w-md w-full mx-4 shadow-2xl border border-slate-100">
        <div class="text-center">
            <div class="mb-6 relative">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto pulse-amber">
                    <svg class="w-10 h-10 text-amber-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Processing Your Payment</h3>
            <p class="text-sm text-slate-500 mb-6">Please wait while we confirm your PayPal payment...</p>
            
            <div class="space-y-4 mb-8">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Time Elapsed</p>
                    <p class="text-xl font-black text-amber-600"><span id="elapsed-time">0</span>s</p>
                </div>
                <div id="status-message" class="text-xs font-bold text-amber-500 uppercase tracking-widest animate-pulse">Checking payment status...</div>
            </div>
            
            <div id="success-actions" style="display:none;">
                <div class="mb-4">
                    <svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-green-600 mb-2">Payment Successful!</h3>
                <p class="text-sm text-slate-500 mb-4">Your subscription is now active.</p>
                <a href="/account/subscription" class="inline-block w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-6 rounded-lg transition">
                    Go to My Subscription
                </a>
            </div>
            
            <div id="error-actions" style="display:none;">
                <div class="mb-4">
                    <svg class="w-16 h-16 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-red-600 mb-2">Payment Issue</h3>
                <p id="error-message" class="text-sm text-slate-500 mb-4">There was an issue with your payment.</p>
                <a href="/account/subscription" class="inline-block w-full bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 px-6 rounded-lg transition">
                    Back to Subscription
                </a>
            </div>
            
            <div id="pending-actions">
                <button id="verify-btn" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-6 rounded-lg transition mb-3">
                    Check Now
                </button>
                <a href="/account/subscription" class="text-sm text-slate-400 hover:text-slate-600">
                    Cancel and go back
                </a>
            </div>
        </div>
    </div>
    
    <script>
        const token = '{$token}';
        const POLL_INTERVAL = 3000; // 3 seconds
        const MAX_POLL_TIME = 120000; // 120 seconds
        let pollCount = 0;
        let startTime = Date.now();
        
        function updateElapsedTime() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('elapsed-time').textContent = elapsed;
        }
        
        function showSuccess() {
            document.getElementById('success-actions').style.display = 'block';
            document.getElementById('pending-actions').style.display = 'none';
            document.getElementById('status-message').textContent = 'Payment confirmed!';
            document.getElementById('status-message').className = 'text-xs font-bold text-green-500 uppercase tracking-widest';
        }
        
        function showError(message) {
            document.getElementById('error-actions').style.display = 'block';
            document.getElementById('pending-actions').style.display = 'none';
            document.getElementById('status-message').textContent = 'Payment failed';
            document.getElementById('status-message').className = 'text-xs font-bold text-red-500 uppercase tracking-widest';
            if (message) {
                document.getElementById('error-message').textContent = message;
            }
        }
        
        function checkStatus() {
            pollCount++;
            updateElapsedTime();
            
            // First try the processing endpoint
            fetch('/subscription/paypal/processing?token=' + token)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.status === 'completed') {
                        showSuccess();
                        return;
                    }
                    
                    // If still pending, try direct verification after some polls
                    if (pollCount > 3) {
                        // Try direct verification
                        fetch('/subscription/paypal/verify?token=' + token)
                            .then(r => r.json())
                            .then(verifyData => {
                                if (verifyData.success && verifyData.status === 'completed') {
                                    showSuccess();
                                } else if (verifyData.status === 'pending') {
                                    document.getElementById('status-message').textContent = 'Still verifying with PayPal...';
                                } else {
                                    showError(verifyData.message);
                                }
                            })
                            .catch(err => {
                                console.error('Verify error:', err);
                            });
                    }
                })
                .catch(err => {
                    console.error('Status check error:', err);
                });
        }
        
        // Auto-poll
        const pollInterval = setInterval(function() {
            const elapsed = Date.now() - startTime;
            if (elapsed > MAX_POLL_TIME) {
                clearInterval(pollInterval);
                document.getElementById('status-message').textContent = 'Taking longer than expected...';
                return;
            }
            checkStatus();
        }, POLL_INTERVAL);
        
        // Manual check button
        document.getElementById('verify-btn').addEventListener('click', function() {
            checkStatus();
        });
        
        // Initial check
        setTimeout(checkStatus, 1000);
    </script>
</body>
</html>
HTML;
    
    return response($html)->header('Content-Type', 'text/html');
});

// Original return route - now redirects to processing page
Route::get('/subscription/paypal/return', function (Request $request) {
    try {
        \Log::info('PayPal Return Received', $request->all());
        
        $token = $request->get('token');
        $PayerID = $request->get('PayerID');
        
        if (!$token) {
            \Flash::error('Invalid PayPal return: missing token');
            return Redirect::to('/account/subscription');
        }
        
        // Find the transaction by order ID
        $existingTransaction = SubscriptionTransaction::where('transaction_id', $token)->first();

        if (!$existingTransaction) {
            \Log::error('PayPal return: Transaction not found for token: ' . $token);
            \Flash::error('Transaction not found');
            return Redirect::to('/account/subscription');
        }

        // If already completed, redirect to success
        if ($existingTransaction->status === 'completed') {
            \Flash::success('Payment already completed');
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

        // NEW: Redirect to processing page that will poll for status
        // Instead of trying to capture immediately, we let the webhook or polling handle it
        return Redirect::to('/subscription/paypal/processing-page?token=' . $token);
        
    } catch (\Exception $e) {
        \Log::error('PayPal Return Error: ' . $e->getMessage());
        \Flash::error('Payment processing failed');
        return Redirect::to('/account/subscription');
    }
});

Route::get('/subscription/paypal/cancel', function (Request $request) {
    \Log::info('PayPal Payment Cancelled', $request->all());
    
    $token = $request->get('token');
    
    // Update transaction status if exists
    if ($token) {
        $transaction = SubscriptionTransaction::where('transaction_id', $token)->first();
        if ($transaction) {
            $transaction->status = SubscriptionTransaction::STATUS_FAILED;
            $metadata = $transaction->metadata ?? [];
            $metadata['failure_reason'] = 'Payment cancelled by user';
            $transaction->metadata = $metadata;
            $transaction->save();
        }
    }
    
    \Flash::info('Payment was cancelled');
    return Redirect::to('/account/subscription');
});

Route::post('/api/paypal/webhook', function (Request $request) {

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
            $existingEvent = \Majos\Sellers\Models\SubscriptionTransaction::where('metadata', 'LIKE', '%"paypal_event_id":"' . $eventId . '"%')->first();
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
            $signatureVerified = verifyPayPalSignature($paypalConfig, $payload, $transmissionId, $timestamp, $webhookId);
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
                    $paypalHelper = new \Majos\Sellers\Classes\Payments\PayPalProvider();
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
                        'amount' => $resource['amount'] ?? null,
                        'currency' => $resource['amount']['currency_code'] ?? null,
                        // Store complete resource data for comprehensive record
                        'resource_data' => $resource,
                        'supplementary_data' => $resource['supplementary_data'] ?? null,
                        'links' => $resource['links'] ?? null,
                        'invoice_id' => $resource['invoice_id'] ?? null,
                        'final_capture' => $resource['final_capture'] ?? null,
                        'seller_receivable_breakdown' => $resource['seller_receivable_breakdown'] ?? null,
                        'raw_payload' => $payload,
                    ];
                    $metadata['payment_completed_at'] = now()->toIso8601String();
                    $transaction->metadata = $metadata;
                    $transaction->save();
                    
                    // Complete the payment
                    $service = new SubscriptionService();
                    $service->completePayment($transaction->transaction_id, 'paypal', true, [
                        'raw' => $payload,
                        'status' => 'completed',
                        'capture_id' => $captureId,
                        'paypal' => $summary
                    ]);
                    
                    \Log::info('PayPal subscription activated via webhook', [
                        'transaction_id' => $transaction->transaction_id,
                        'capture_id' => $captureId,
                        'payer_email' => $summary['payer_email'] ?? null
                    ]);
                } else if ($transaction && $transaction->status === 'completed') {
                    \Log::info('PayPal payment already completed', ['transaction_id' => $transaction->transaction_id]);
                } else {
                    \Log::warning('PayPal webhook: TRANSACTION NOT FOUND for event', [
                        'event_type' => $eventType,
                        'resource_id' => $captureId,
                        'custom_id' => $customId,
                        'event_id' => $eventId
                    ]);
                }
                break;
                
            case 'PAYMENT.CAPTURE.PENDING':
                // Handle when payment goes into pending status (e.g., after initial capture)
                $captureId = $resource['id'] ?? '';
                $statusReason = $resource['status_details']['reason'] ?? 'unknown';
                
                \Log::info('PayPal payment pending', [
                    'capture_id' => $captureId,
                    'reason' => $statusReason,
                    'event_id' => $eventId
                ]);
                
                // Find transaction using helper method
                if ($captureId) {
                    $transaction = SubscriptionTransaction::findByCaptureId($captureId);
                    if ($transaction) {
                        $metadata = $transaction->metadata ?? [];
                        
                        // Extract rich summary
                        $paypalHelper = new \Majos\Sellers\Classes\Payments\PayPalProvider();
                        $summary      = $paypalHelper->extractPayPalPaymentSummary(['purchase_units' => [['payments' => ['captures' => [$resource]]]]]);

                        $metadata['paypal'] = $summary;
                        $metadata['paypal_webhook'] = [
                            'event_type' => $eventType,
                            'event_id' => $eventId,
                            'received_at' => now()->toIso8601String(),
                            'capture_id' => $captureId,
                            'status' => $resource['status'] ?? null,
                            'status_reason' => $statusReason,
                            'amount' => $resource['amount'] ?? null,
                            'currency' => $resource['amount']['currency_code'] ?? null,
                            'raw_payload' => $payload,
                        ];
                        $metadata['payment_pending_at'] = now()->toIso8601String();
                        $transaction->metadata = $metadata;
                        $transaction->status = SubscriptionTransaction::STATUS_PENDING;
                        $transaction->save();
                        
                        \Log::info('PayPal transaction set to pending via webhook', [
                            'transaction_id' => $transaction->transaction_id,
                            'capture_id' => $captureId,
                            'reason' => $statusReason,
                            'payer_email' => $summary['payer_email'] ?? null
                        ]);
                    }
                }
                break;
                
            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $captureId = $resource['id'] ?? '';
                \Log::warning('PayPal payment denied', ['capture_id' => $captureId]);
                
                // Find transaction using helper method
                $transaction = SubscriptionTransaction::findByCaptureId($captureId);
                if ($transaction) {
                    $transaction->status = SubscriptionTransaction::STATUS_FAILED;
                    $metadata = $transaction->metadata ?? [];
                    
                    // Extract rich summary
                    $paypalHelper = new \Majos\Sellers\Classes\Payments\PayPalProvider();
                    $summary      = $paypalHelper->extractPayPalPaymentSummary(['purchase_units' => [['payments' => ['captures' => [$resource]]]]]);

                    $metadata['paypal'] = $summary;
                    $metadata['paypal_webhook'] = [
                        'event_type' => $eventType,
                        'event_id' => $eventId,
                        'received_at' => now()->toIso8601String(),
                        'capture_id' => $captureId,
                        'status' => $resource['status'] ?? null,
                        'status_reason' => $resource['status_details']['reason'] ?? null,
                        'raw_payload' => $payload,
                    ];
                    $metadata['failure_reason'] = 'Payment denied by PayPal: ' . ($resource['status_details']['reason'] ?? 'Unknown');
                    $transaction->metadata = $metadata;
                    $transaction->save();

                    \Log::info('PayPal payment failed via webhook', [
                        'transaction_id' => $transaction->transaction_id,
                        'capture_id'    => $captureId,
                        'reason'        => $resource['status_details']['reason'] ?? 'Unknown',
                        'payer_email'   => $summary['payer_email'] ?? null
                    ]);
                }
                break;
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                $captureId = $resource['id'] ?? '';
                \Log::info('PayPal payment refunded', ['capture_id' => $captureId]);
                
                $transaction = SubscriptionTransaction::findByCaptureId($captureId);
                if ($transaction) {
                    $transaction->status = SubscriptionTransaction::STATUS_REFUNDED;
                    $metadata = $transaction->metadata ?? [];
                    $metadata['paypal_webhook'] = [
                        'event_type' => $eventType,
                        'event_id' => $eventId,
                        'received_at' => now()->toIso8601String(),
                        'capture_id' => $captureId,
                        'status' => $resource['status'] ?? null,
                        'amount_refunded' => $resource['amount']['value'] ?? null,
                        'currency' => $resource['amount']['currency_code'] ?? null,
                        'raw_payload' => $payload,
                    ];
                    $metadata['refunded_at'] = now()->toIso8601String();
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
});
