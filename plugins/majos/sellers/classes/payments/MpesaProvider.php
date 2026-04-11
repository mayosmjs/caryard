<?php namespace Majos\Sellers\Classes\Payments;

use Majos\Sellers\Models\SellerSubscription;
use Majos\Sellers\Models\SubscriptionTransaction;

/**
 * M-Pesa Payment Provider
 * Implements PaymentProviderInterface for M-Pesa STK Push
 */
class MpesaProvider implements PaymentProviderInterface
{
    protected $config = [];
    protected $isInitialized = false;

    /**
     * Initialize M-Pesa with configuration
     */
    public function initialize(array $config): bool
    {
        $required = ['consumer_key', 'consumer_secret', 'business_short_code', 'passkey'];
        
        foreach ($required as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }

        $this->config = $config;
        $this->isInitialized = true;
        return true;
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'M-Pesa';
    }

    /**
     * Create a payment via STK Push
     */
    public function createPayment(SellerSubscription $subscription, array $params): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('M-Pesa provider not initialized');
        }

        $phone = $params['phone'] ?? '';
        $amount = $params['amount'] ?? 0;
        $accountReference = $params['account_reference'] ?? 'SUB-' . $subscription->id;
        $transactionDesc = $params['description'] ?? 'Subscription Payment';

        // Validate phone number (Kenyan format)
        if (empty($phone)) {
            return PaymentResult::failure('Phone number is required');
        }

        // Format phone to 254XXXXXXXXX
        $phone = $this->formatPhone($phone);

        // Get access token
        $token = $this->getAccessToken();
        if (!$token) {
            return PaymentResult::failure('Failed to get M-Pesa access token');
        }

        // Make STK Push request
        $response = $this->stkPushRequest($token, $phone, $amount, $accountReference, $transactionDesc);

        if (isset($response['CheckoutRequestID'])) {
            // Create pending transaction
            $transaction = new SubscriptionTransaction();
            $transaction->subscription_id = $subscription->id;
            $transaction->provider = SubscriptionTransaction::PROVIDER_MPESA;
            $transaction->transaction_id = $response['CheckoutRequestID'];
            $transaction->amount = $amount;
            $transaction->currency = 'KES';
            $transaction->status = SubscriptionTransaction::STATUS_PENDING;
            $transaction->payment_type = SubscriptionTransaction::TYPE_SUBSCRIPTION_CREATE;
            $transaction->customer_details = [
                'phone' => $phone,
            ];
            $transaction->metadata = [
                'account_reference' => $accountReference,
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            ];
            $transaction->initiated_by = $params['initiated_by'] ?? SubscriptionTransaction::INITIATED_BY_FRONTEND;
            $transaction->save();

            return PaymentResult::success(
                'STK Push sent to your phone',
                $response['CheckoutRequestID'],
                [
                    'checkout_request_id' => $response['CheckoutRequestID'],
                    'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                ]
            );
        }

        $errorMessage = $response['errorMessage'] ?? 'Payment request failed';
        return PaymentResult::failure($errorMessage);
    }

    /**
     * Verify a payment (check status)
     */
    public function verifyPayment(string $transactionId): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('M-Pesa provider not initialized');
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return PaymentResult::failure('Failed to get M-Pesa access token');
        }

        $response = $this->stkQueryRequest($token, $transactionId);

        // If Daraja returns errorCode, transaction is still processing or transiently not found
        if (isset($response['errorCode'])) {
            $errorMessage = $response['errorMessage'] ?? 'API Error';
            $pendingCodes = ['500.001.1001', '404.001.1002'];
            
            // Check for codes or specific "processing" text in the error message
            if (in_array($response['errorCode'], $pendingCodes) || stripos($errorMessage, 'still under processing') !== false) {
                return PaymentResult::failure('Payment is still processing', ['is_pending' => true, 'raw' => $response]);
            }
            
            return PaymentResult::failure($errorMessage, ['is_failed' => true, 'raw' => $response]);
        }

        // Handle Spike Arrest / Rate Limit Faults (common in Sandbox)
        if (isset($response['fault'])) {
            $faultCode = $response['fault']['detail']['errorcode'] ?? '';
            if (strpos($faultCode, 'SpikeArrestViolation') !== false) {
                return PaymentResult::failure('Rate limited by Safaricom, waiting...', ['is_pending' => true, 'raw' => $response]);
            }
            return PaymentResult::failure($response['fault']['faultstring'] ?? 'Provider Error', ['is_failed' => true, 'raw' => $response]);
        }

        if (isset($response['ResultCode'])) {
            if ($response['ResultCode'] == 0) {
                return PaymentResult::success(
                    'Payment successful',
                    $transactionId,
                    [
                        'result_code' => $response['ResultCode'],
                        'result_desc' => $response['ResultDesc'] ?? '',
                        'raw' => $response,
                    ]
                );
            } else {
                return PaymentResult::failure(
                    $response['ResultDesc'] ?? 'Payment failed',
                    ['result_code' => $response['ResultCode'], 'is_failed' => true]
                );
            }
        }

        return PaymentResult::failure('Unable to verify payment status', ['is_failed' => true, 'raw' => $response]);
    }

    /**
     * Process webhook/callback from M-Pesa
     */
    public function processWebhook(array $data): PaymentResult
    {
        // This is called when M-Pesa sends callback
        // The data contains transaction status
        
        $resultCode = $data['Body']['stkCallback']['ResultCode'] ?? null;
        $checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'] ?? '';
        
        if ($resultCode === 0) {
            // Payment successful
            $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? 0;
            $mpesaReceiptNumber = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? '';
            $phone = $data['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'] ?? '';

            return PaymentResult::success(
                'Payment confirmed',
                $checkoutRequestId,
                [
                    'mpesa_receipt' => $mpesaReceiptNumber,
                    'amount' => $amount,
                    'phone' => $phone,
                ]
            );
        }

        return PaymentResult::failure(
            $data['Body']['stkCallback']['ResultDesc'] ?? 'Payment failed',
            ['result_code' => $resultCode]
        );
    }

    /**
     * Refund a transaction (B2C)
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('M-Pesa provider not initialized');
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return PaymentResult::failure('Failed to get M-Pesa access token');
        }

        // Note: B2C requires a different setup/shortcode
        // This is a basic implementation
        $response = $this->b2cRequest($token, $transactionId, $amount);

        if (isset($response['ConversationID'])) {
            return PaymentResult::success(
                'Refund initiated',
                $response['ConversationID'],
                $response
            );
        }

        return PaymentResult::failure('Refund request failed');
    }

    /**
     * Get supported payment types
     */
    public function getSupportedPaymentTypes(): array
    {
        return [
            'stk_push' => 'STK Push (Mobile)',
            'b2c' => 'B2C (Business to Customer)',
        ];
    }

    /**
     * Validate configuration
     */
    public function validateConfig(): bool
    {
        return $this->isInitialized;
    }

    // ============== Private Helper Methods ==============

    /**
     * Format phone number to 254XXXXXXXXX
     */
    protected function formatPhone(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add 254 if it starts with 0 or 7
        if (strlen($phone) === 9) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '254' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Get M-Pesa OAuth access token
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = 'mpesa_token_' . md5($this->config['consumer_key'] ?? '');
        
        return \Cache::remember($cacheKey, 55 * 60, function () {
            $url = $this->config['environment'] === 'sandbox'
                ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
                : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

            $credentials = base64_encode(
                $this->config['consumer_key'] . ':' . $this->config['consumer_secret']
            );

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $credentials,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) return null;
            $data = json_decode($response, true);
            
            return $data['access_token'] ?? null;
        });
    }

    /**
     * STK Push Request
     */
    protected function stkPushRequest(string $token, string $phone, float $amount, string $accountRef, string $desc): array
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = date('YmdHis');
        $password = base64_encode(
            $this->config['business_short_code'] . 
            $this->config['passkey'] . 
            $timestamp
        );

        $data = [
            'BusinessShortCode' => $this->config['business_short_code'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount),
            'PartyA' => $phone,
            'PartyB' => $this->config['business_short_code'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->config['callback_url'] ?? '',
            'AccountReference' => $accountRef,
            'TransactionDesc' => $desc,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * STK Query Request (check status)
     */
    protected function stkQueryRequest(string $token, string $checkoutRequestId): array
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
            : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';

        $timestamp = date('YmdHis');
        $password = base64_encode(
            $this->config['business_short_code'] . 
            $this->config['passkey'] . 
            $timestamp
        );

        $data = [
            'BusinessShortCode' => $this->config['business_short_code'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * B2C Request (for refunds)
     */
    protected function b2cRequest(string $token, string $phone, float $amount): array
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest'
            : 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';

        $data = [
            'InitiatorName' => $this->config['initiator_name'] ?? '',
            'SecurityCredential' => $this->config['security_credential'] ?? '',
            'CommandID' => 'BusinessPayment',
            'Amount' => round($amount),
            'PartyA' => $this->config['business_short_code'],
            'PartyB' => $phone,
            'Remarks' => 'Refund',
            'QueueTimeOutURL' => $this->config['b2c_timeout_url'] ?? '',
            'ResultURL' => $this->config['b2c_result_url'] ?? '',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}