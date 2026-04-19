<?php

namespace Majos\Sellers\Controllers;

use Illuminate\Http\Request;
use Majos\Sellers\Classes\SubscriptionService;
use Majos\Sellers\Models\SubscriptionTransaction;

class MpesaWebhookController
{

    /**
 * Allowed IPs for payment callback/webhook requests
 * These are the API gateway IPs that can send payment notifications
 */
function getAllowedCallbackIPs(): array
{
    return [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
    ];
}

/**
 * Verify if the request comes from an allowed IP
 */
function verifyCallbackIP(): bool
{
    $clientIP = request()->ip();
    $allowedIPs = $this->getAllowedCallbackIPs();
    
    // For development, allow local requests
    if (in_array($clientIP, ['127.0.0.1', '::1', 'localhost'])) {
        return true;
    }
    
    return in_array($clientIP, $allowedIPs);
}

    /**
     * Handle M-Pesa callback notification
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Verify IP whitelist
            if (!$this->verifyCallbackIP()) {
                \Log::warning('M-Pesa callback: IP not allowed', ['ip' => request()->ip()]);
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

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
    }
}
