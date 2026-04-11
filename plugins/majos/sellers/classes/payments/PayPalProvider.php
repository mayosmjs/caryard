<?php namespace Majos\Sellers\Classes\Payments;

use Majos\Sellers\Models\SellerSubscription;
use Majos\Sellers\Models\SubscriptionTransaction;
use Log;

/**
 * PayPal Payment Provider
 * Implements PaymentProviderInterface for PayPal payments
 */
class PayPalProvider implements PaymentProviderInterface
{
    protected $config = [];
    protected $isInitialized = false;

    /**
     * Initialize PayPal with configuration
     */
    public function initialize(array $config): bool
    {
        $required = ['client_id', 'secret'];
        
        $missing = [];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            Log::warning('PayPal initialization failed - missing config: ' . implode(', ', $missing));
            return false;
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
        return 'PayPal';
    }

    /**
     * Create a PayPal order
     */
    public function createPayment(SellerSubscription $subscription, array $params): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('PayPal provider not initialized');
        }

        $amount = $params['amount'] ?? 0;
        $currency = $params['currency'] ?? 'USD';
        $description = $params['description'] ?? 'Subscription Payment';

        if ($amount <= 0) {
            return PaymentResult::failure('Invalid amount');
        }

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return PaymentResult::failure('Failed to get PayPal access token');
        }

        // Create order
        $orderData = [
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $params['return_url'] ?? $this->config['return_url'] ?? 'https://example.com/subscription/paypal/return',
                'cancel_url' => $params['cancel_url'] ?? $this->config['cancel_url'] ?? 'https://example.com/subscription/paypal/cancel',
            ],
            'purchase_units' => [
                [
                    'reference_id' => 'SUB-' . $subscription->id,
                    'custom_id'    => 'SUB-' . $subscription->id, // used by webhook to locate transaction
                    'description'  => $description,
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
        ];

        \Log::info('PayPal createOrder request', [
            'amount' => $amount,
            'currency' => $currency,
            'return_url' => $orderData['application_context']['return_url'],
            'cancel_url' => $orderData['application_context']['cancel_url'],
        ]);

        $response = $this->createOrder($accessToken, $orderData);

        \Log::info('PayPal createOrder response', $response);

        if (isset($response['id'])) {
            // Find approval URL
            $approveUrl = null;
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveUrl = $link['href'];
                    break;
                }
            }

            // Create pending transaction
            $transaction = new SubscriptionTransaction();
            $transaction->subscription_id = $subscription->id;
            $transaction->provider = SubscriptionTransaction::PROVIDER_PAYPAL;
            $transaction->transaction_id = $response['id'];
            $transaction->amount = $amount;
            $transaction->currency = $currency;
            $transaction->status = SubscriptionTransaction::STATUS_PENDING;
            $transaction->payment_type = SubscriptionTransaction::TYPE_SUBSCRIPTION_CREATE;
            $transaction->metadata = [
                'order_id'      => $response['id'],
                'paypal_status' => $response['status'],
                'amount'        => $amount,
                'currency'      => $currency,
                'description'   => $description,
                'initiated_at'  => date('Y-m-d H:i:s'),
            ];
            $transaction->initiated_by = $params['initiated_by'] ?? SubscriptionTransaction::INITIATED_BY_FRONTEND;
            $transaction->save();

            return PaymentResult::success(
                'PayPal order created',
                $response['id'],
                [
                    'order_id' => $response['id'],
                    'status' => $response['status'],
                ],
                $approveUrl
            );
        }

        $error = $response['error'] ?? 'Failed to create PayPal order';
        return PaymentResult::failure($error);
    }

    /**
     * Verify/Capture a payment
     */
    public function verifyPayment(string $transactionId): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('PayPal provider not initialized');
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return PaymentResult::failure('Failed to get PayPal access token');
        }

        // Capture the order - include request data for logging
        $captureRequest = [
            'method' => 'POST',
            'endpoint' => "/v2/checkout/orders/{$transactionId}/capture",
            'body' => '{}',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $captureResponse = $this->captureOrder($accessToken, $transactionId);

        // Check for successful capture
        $orderStatus = $captureResponse['status'] ?? '';
        $capture = $captureResponse['purchase_units'][0]['payments']['captures'][0] ?? null;
        $captureStatus = $capture['status'] ?? '';
        $captureReason = $capture['status_details']['reason'] ?? null;

        if ($orderStatus === 'COMPLETED' && $captureStatus === 'COMPLETED') {
            $captureId = $capture['id'] ?? null;

            // Extract rich summary and persist it
            $summary = $this->extractPayPalPaymentSummary($captureResponse);
            $this->persistPayPalDetails($transactionId, $summary, $captureResponse);

            return PaymentResult::success(
                'Payment completed',
                $transactionId,
                [
                    'status'           => $captureStatus,
                    'paypal'           => $summary,
                    'capture_id'       => $captureId,
                    'capture_request'  => $captureRequest,
                    'capture_response' => $captureResponse,
                ]
            );
        }

        // Handle pending capture (e.g., PENDING_REVIEW from PayPal fraud checks)
        if ($orderStatus === 'COMPLETED' && $captureStatus === 'PENDING') {
            $summary = $this->extractPayPalPaymentSummary($captureResponse);
            $this->persistPayPalDetails($transactionId, $summary, $captureResponse);
            
            return PaymentResult::failure(
                'Payment pending review: ' . ($captureReason ?? 'Unknown reason'),
                [
                    'paypal'           => $summary,
                    'capture_request'  => $captureRequest,
                    'capture_response' => $captureResponse,
                    'order_status'     => $orderStatus,
                    'capture_status'   => $captureStatus,
                    'capture_reason'   => $captureReason,
                    'capture_id'       => $capture['id'] ?? null,
                    'is_pending'       => true,
                ]
            );
        }

        // Extract summary even on failure if we have a response
        if ($captureResponse) {
            $summary = $this->extractPayPalPaymentSummary($captureResponse);
            $this->persistPayPalDetails($transactionId, $summary, $captureResponse);
        }

        // Payment not completed - return failure with full response for logging
        return PaymentResult::failure(
            $captureResponse['status'] ?? 'Payment not completed',
            [
                'capture_request' => $captureRequest,
                'capture_response' => $captureResponse,
                'error_details' => $captureResponse['details'] ?? null,
                'error_message' => $captureResponse['message'] ?? null,
            ]
        );
    }

    /**
     * Process webhook from PayPal
     */
    public function processWebhook(array $data): PaymentResult
    {
        // Verify webhook signature in production
        // $verified = $this->verifyWebhookSignature($data);
        
        $eventType = $data['event_type'] ?? '';
        
        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
                // Order was approved by customer
                return PaymentResult::success('Order approved', $data['resource']['id'] ?? '', $data);
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                // Payment completed
                $resource = $data['resource'] ?? [];
                return PaymentResult::success(
                    'Payment captured',
                    $resource['id'] ?? '',
                    $resource
                );
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                // Payment refunded
                return PaymentResult::success('Payment refunded', $data['resource']['id'] ?? '', $data);
                
            default:
                return PaymentResult::failure('Unknown event type: ' . $eventType);
        }
    }

    /**
     * Refund a transaction
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('PayPal provider not initialized');
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return PaymentResult::failure('Failed to get PayPal access token');
        }

        // First we need to find the capture ID from the order
        // In production, you'd store this in the transaction metadata
        $refundData = [];
        if ($amount) {
            $refundData['amount'] = [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => 'USD',
            ];
        }

        // This is simplified - you'd need the capture_id
        return PaymentResult::failure('Refund requires capture ID');
    }

    /**
     * Get supported payment types
     */
    public function getSupportedPaymentTypes(): array
    {
        return [
            'paypal' => 'PayPal Account',
            'card' => 'Credit/Debit Card via PayPal',
        ];
    }

    /**
     * Validate configuration
     */
    public function validateConfig(): bool
    {
        return $this->isInitialized;
    }

    // ============== PayPal Data Extraction ==============

    /**
     * Extract a structured, human-readable summary from a PayPal Order/Capture object.
     *
     * Captures payer identity, capture details, shipping, and provider status.
     * Made public so external callers (e.g. webhook route handlers) can reuse it.
     */
    public function extractPayPalPaymentSummary(array $data): array
    {
        $payer = $data['payer'] ?? [];
        $pUnit = $data['purchase_units'][0] ?? [];
        $capture = $pUnit['payments']['captures'][0] ?? [];
        $amount = $capture['amount'] ?? $pUnit['amount'] ?? [];
        
        // Fee breakdown if available
        $breakdown = $capture['seller_receivable_breakdown'] ?? [];

        return [
            // ── Payer identity ────────────────────────────────────────────────────
            'payer_name'        => ($payer['name']['given_name'] ?? '') . ' ' . ($payer['name']['surname'] ?? ''),
            'payer_email'       => $payer['email_address'] ?? null,
            'payer_id'          => $payer['payer_id'] ?? null,
            
            // ── Shipping address ────────────────────────────────────────────────
            'shipping_name'     => $pUnit['shipping']['name']['full_name'] ?? null,
            'shipping_address'  => $pUnit['shipping']['address'] ?? null,

            // ── Payment identifiers ───────────────────────────────────────────────
            'order_id'          => $data['id'] ?? null,
            'capture_id'        => $capture['id'] ?? null,
            'capture_status'    => $capture['status'] ?? null,

            // ── Amount & Fees ────────────────────────────────────────────────────
            'amount_value'      => $amount['value'] ?? null,
            'amount_currency'   => $amount['currency_code'] ?? null,
            'paypal_fee'        => $breakdown['paypal_fee']['value'] ?? null,
            'net_amount'        => $breakdown['net_amount']['value'] ?? null,

            // ── Provider status ──────────────────────────────────────────────────
            'order_status'      => $data['status'] ?? null,
            'intent'            => $data['intent'] ?? null,
            'recorded_at'       => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Persist the PayPal payment summary and raw response directly
     * into the transaction row.
     */
    protected function persistPayPalDetails(string $transactionId, array $summary, array $rawResponse): void
    {
        $txn = SubscriptionTransaction::where('transaction_id', $transactionId)->first();
        if (!$txn) {
            \Log::warning('PayPalProvider: could not persist details — transaction not found', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        $meta = $txn->metadata ?? [];
        $meta['paypal'] = $summary;
        $meta['paypal_raw_response'] = $rawResponse;
        
        // Always ensure capture_id is at top level for finding transactions
        if (!empty($summary['capture_id'])) {
            $meta['capture_id'] = $summary['capture_id'];
        }
        
        $txn->metadata = $meta;
        $txn->save();

        \Log::info('PayPalProvider: payment details persisted to metadata', [
            'transaction_id' => $transactionId,
            'paypal_status'  => $summary['order_status'],
            'payer_email'    => $summary['payer_email'],
            'capture_id'     => $summary['capture_id'],
        ]);
    }

    // ============== Private Helper Methods ==============

    /**
     * Get PayPal OAuth access token
     */
    protected function getAccessToken(): ?string
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api-m.paypal.com/v1/oauth2/token';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['client_id'] . ':' . $this->config['secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        return $data['access_token'] ?? null;
    }

    /**
     * Create PayPal order
     */
    protected function createOrder(string $accessToken, array $data): array
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            : 'https://api-m.paypal.com/v2/checkout/orders';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * Capture PayPal order
     */
    protected function captureOrder(string $accessToken, string $orderId): array
    {
        $url = $this->config['environment'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $orderId . '/capture'
            : 'https://api-m.paypal.com/v2/checkout/orders/' . $orderId . '/capture';

        $requestBody = '{}';
        
        \Log::info('PayPal captureOrder request', ['order_id' => $orderId, 'url' => $url, 'body' => $requestBody]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \Log::info('PayPal captureOrder response', [
            'order_id' => $orderId,
            'http_code' => $httpCode,
            'response' => $response
        ]);

        return json_decode($response, true) ?? [];
    }
}