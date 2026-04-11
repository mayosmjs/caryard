<?php namespace Majos\Sellers\Classes\Payments;

use Majos\Sellers\Models\SellerSubscription;
use Majos\Sellers\Models\SubscriptionTransaction;

/**
 * Stripe Payment Provider
 * Implements PaymentProviderInterface for Stripe payments
 */
class StripeProvider implements PaymentProviderInterface
{
    protected $config = [];
    protected $isInitialized = false;

    /**
     * Initialize Stripe with configuration
     */
    public function initialize(array $config): bool
    {
        if (empty($config['api_key'])) {
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
        return 'Stripe';
    }

    /**
     * Create a Stripe payment intent
     */
    public function createPayment(SellerSubscription $subscription, array $params): PaymentResult
    {
        if (!$this->isInitialized) {
            return PaymentResult::failure('Stripe provider not initialized');
        }

        $amount = $params['amount'] ?? 0;
        $currency = strtolower($params['currency'] ?? 'usd');
        $description = $params['description'] ?? 'Subscription Payment';

        if ($amount <= 0) {
            return PaymentResult::failure('Invalid amount');
        }

        // Convert amount to cents for Stripe
        $amountInCents = $this->amountToCents($amount, $currency);

        // Create payment intent
        $data = [
            'amount' => $amountInCents,
            'currency' => $currency,
            'description' => $description,
            'metadata' => [
                'subscription_id' => $subscription->id,
                'seller_id' => $subscription->seller_id,
            ],
            'automatic_payment_methods' => [
                'enabled' => 'true',
            ],
        ];

        // Add customer email if provided
        if (!empty($params['email'])) {
            $data['receipt_email'] = $params['email'];
        }

        $response = $this->createPaymentIntent($data);

        if (isset($response['id'])) {
            // Create pending transaction
            $transaction = new SubscriptionTransaction();
            $transaction->subscription_id = $subscription->id;
            $transaction->provider = SubscriptionTransaction::PROVIDER_STRIPE;
            $transaction->transaction_id = $response['id'];
            $transaction->amount = $amount;
            $transaction->currency = strtoupper($currency);
            $transaction->status = SubscriptionTransaction::STATUS_PENDING;
            $transaction->payment_type = SubscriptionTransaction::TYPE_SUBSCRIPTION_CREATE;
            $transaction->metadata = [
                'payment_intent_id' => $response['id'],
                'client_secret'     => $response['client_secret'] ?? '',
                'stripe_status'     => $response['status'],
                'amount'            => $amount,
                'currency'          => strtoupper($currency),
                'description'       => $description,
                'initiated_at'      => date('Y-m-d H:i:s'),
            ];
            $transaction->initiated_by = $params['initiated_by'] ?? SubscriptionTransaction::INITIATED_BY_FRONTEND;
            $transaction->save();

            // Get the client secret for frontend to complete payment
            $clientSecret = $response['client_secret'] ?? '';

            return PaymentResult::success(
                'Payment intent created',
                $response['id'],
                [
                    'payment_intent_id' => $response['id'],
                    'client_secret' => $clientSecret,
                    'status' => $response['status'],
                ],
                null
            );
        }

        $error = $response['error']['message'] ?? 'Failed to create payment intent';
        return PaymentResult::failure($error);
    }

    /**
     * Verify a payment (check status)
     */
    public function verifyPayment(string $transactionId): PaymentResult
    {
        if (!$this->isInitialized) {
            \Log::error('Stripe provider not initialized');
            return PaymentResult::failure('Stripe provider not initialized');
        }

        \Log::info('Verifying Stripe payment', ['transaction_id' => $transactionId]);

        // Prepare request data for logging
        $retrieveRequest = [
            'method' => 'GET',
            'endpoint' => "/v1/payment_intents/{$transactionId}",
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        $response = $this->retrievePaymentIntent($transactionId);

        \Log::info('Stripe API response', [
            'has_id' => isset($response['id']),
            'status' => $response['status'] ?? 'no status',
            'error' => $response['error'] ?? null
        ]);

        if (isset($response['id'])) {
            $status  = $response['status'];
            // Extract rich payer/card/outcome data and persist it immediately so
            // it is captured regardless of which higher-level path calls us.
            $summary = $this->extractStripePaymentSummary($response);
            $this->persistStripeDetails($transactionId, $summary, $response);

            if ($status === 'succeeded') {
                return PaymentResult::success(
                    'Payment successful',
                    $transactionId,
                    [
                        'status'            => $status,
                        'stripe'            => $summary,
                        'amount'            => $this->centsToAmount($response['amount'], $response['currency']),
                        'currency'          => strtoupper($response['currency']),
                        'retrieve_request'  => $retrieveRequest,
                        'retrieve_response' => $response,
                    ]
                );
            }

            if (in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action'])) {
                return PaymentResult::failure(
                    'Payment requires action: ' . $status,
                    [
                        'status'            => $status,
                        'stripe'            => $summary,
                        'retrieve_request'  => $retrieveRequest,
                        'retrieve_response' => $response,
                    ]
                );
            }

            return PaymentResult::failure(
                'Payment status: ' . $status,
                [
                    'status'            => $status,
                    'stripe'            => $summary,
                    'retrieve_request'  => $retrieveRequest,
                    'retrieve_response' => $response,
                ]
            );
        }

        $error = $response['error']['message'] ?? 'Unable to retrieve payment';
        \Log::error('Stripe verification error', ['error' => $error, 'response' => $response]);
        return PaymentResult::failure($error, ['stripe_api_error' => $response['error'] ?? $response]);
    }

    /**
     * Process webhook from Stripe
     */
    public function processWebhook(array $data): PaymentResult
    {
        $eventType = $data['type'] ?? '';
        $eventData = $data['data']['object'] ?? [];

        switch ($eventType) {
            case 'payment_intent.succeeded':
                return PaymentResult::success(
                    'Payment successful',
                    $eventData['id'] ?? '',
                    $eventData
                );

            case 'payment_intent.payment_failed':
                $error = $eventData['last_payment_error']['message'] ?? 'Payment failed';
                return PaymentResult::failure($error, $eventData);

            case 'charge.refunded':
                return PaymentResult::success(
                    'Payment refunded',
                    $eventData['id'] ?? '',
                    $eventData
                );

            case 'charge.dispute.created':
                return PaymentResult::failure('Payment dispute created', $eventData);

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
            return PaymentResult::failure('Stripe provider not initialized');
        }

        $data = [];
        
        if ($amount !== null) {
            // Amount must be in cents
            $data['amount'] = $this->amountToCents($amount, 'usd');
        }

        $response = $this->createRefund($transactionId, $data);

        if (isset($response['id'])) {
            return PaymentResult::success(
                'Refund processed',
                $response['id'],
                $response
            );
        }

        $error = $response['error']['message'] ?? 'Refund failed';
        return PaymentResult::failure($error);
    }

    /**
     * Get supported payment types
     */
    public function getSupportedPaymentTypes(): array
    {
        return [
            'card' => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'klarna' => 'Klarna',
            'afterpay_clearpay' => 'Afterpay/Clearpay',
        ];
    }

    /**
     * Validate configuration
     */
    public function validateConfig(): bool
    {
        return $this->isInitialized;
    }

    // ============== Stripe Data Extraction ==============

    /**
     * Extract a structured, human-readable summary from a Stripe PaymentIntent object.
     *
     * Captures payer identity, card details, risk outcome, and failure information
     * for BOTH successful and failed payments. Made public so external callers
     * (e.g. webhook route handlers) can reuse it without needing a full provider init.
     */
    public function extractStripePaymentSummary(array $pi): array
    {
        // First charge attached to the PaymentIntent (present after capture)
        $charge     = $pi['charges']['data'][0] ?? [];
        $billing    = $charge['billing_details'] ?? [];
        $card       = $charge['payment_method_details']['card'] ?? [];
        $outcome    = $charge['outcome'] ?? [];

        // For failed payments Stripe populates last_payment_error
        $lastError  = $pi['last_payment_error'] ?? [];
        $errPm      = $lastError['payment_method'] ?? [];
        $errCard    = $errPm['card'] ?? [];
        $errBilling = $errPm['billing_details'] ?? [];

        return [
            // ── Payer identity ────────────────────────────────────────────────────
            'customer_name'     => $billing['name']  ?? $errBilling['name']  ?? null,
            'customer_email'    => $pi['receipt_email']
                                    ?? $billing['email']   ?? $errBilling['email']  ?? null,
            'billing_address'   => !empty($billing['address'])
                                    ? array_filter($billing['address'])
                                    : null,

            // ── Payment identifiers ───────────────────────────────────────────────
            'payment_intent_id' => $pi['id'] ?? null,
            'payment_method_id' => $pi['payment_method'] ?? $errPm['id'] ?? null,
            'charge_id'         => $charge['id'] ?? $lastError['charge'] ?? null,
            'receipt_url'       => $charge['receipt_url'] ?? null,

            // ── Card details ──────────────────────────────────────────────────────
            'card_brand'        => $card['brand']      ?? $errCard['brand']      ?? null,
            'card_last4'        => $card['last4']      ?? $errCard['last4']      ?? null,
            'card_exp_month'    => $card['exp_month']  ?? $errCard['exp_month']  ?? null,
            'card_exp_year'     => $card['exp_year']   ?? $errCard['exp_year']   ?? null,
            'card_country'      => $card['country']    ?? $errCard['country']    ?? null,
            'card_funding'      => $card['funding']    ?? $errCard['funding']    ?? null,
            'card_fingerprint'  => $card['fingerprint'] ?? null,

            // ── Amount ────────────────────────────────────────────────────────────
            'amount'            => isset($pi['amount'], $pi['currency'])
                                    ? $this->centsToAmount((int) $pi['amount'], $pi['currency'])
                                    : null,
            'currency'          => isset($pi['currency']) ? strtoupper($pi['currency']) : null,
            'stripe_status'     => $pi['status'] ?? null,

            // ── Outcome (populated on successful charges) ─────────────────────────
            'network_status'    => $outcome['network_status']  ?? null,
            'risk_level'        => $outcome['risk_level']      ?? null,
            'risk_score'        => $outcome['risk_score']      ?? null,
            'seller_message'    => $outcome['seller_message']  ?? null,

            // ── Failure details (populated when payment is declined / failed) ─────
            'failure_code'      => $lastError['code']         ?? null,
            'decline_code'      => $lastError['decline_code'] ?? null,
            'failure_message'   => $lastError['message']      ?? null,
            'failure_type'      => $lastError['type']         ?? null,

            // ── Timestamps ────────────────────────────────────────────────────────
            'captured_at'       => !empty($charge['created'])
                                    ? date('Y-m-d H:i:s', (int) $charge['created'])
                                    : null,
            'pi_created_at'     => !empty($pi['created'])
                                    ? date('Y-m-d H:i:s', (int) $pi['created'])
                                    : null,
            'recorded_at'       => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Persist the Stripe payment summary and raw PaymentIntent response directly
     * into the transaction row so the data is captured regardless of which
     * higher-level path (completePayment / webhook / onCheckPaymentStatus) runs.
     */
    protected function persistStripeDetails(string $transactionId, array $summary, array $rawResponse): void
    {
        $txn = SubscriptionTransaction::where('transaction_id', $transactionId)->first();
        if (!$txn) {
            \Log::warning('StripeProvider: could not persist details — transaction not found', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        $meta                       = $txn->metadata ?? [];
        $meta['stripe']             = $summary;
        $meta['stripe_raw_response'] = $rawResponse;
        $txn->metadata              = $meta;
        $txn->save();

        \Log::info('StripeProvider: payment details persisted to metadata', [
            'transaction_id'    => $transactionId,
            'stripe_status'     => $summary['stripe_status'],
            'customer_email'    => $summary['customer_email'],
            'card_last4'        => $summary['card_last4'],
            'failure_code'      => $summary['failure_code'],
            'decline_code'      => $summary['decline_code'],
        ]);
    }

    // ============== Private Helper Methods ==============

    /**
     * Convert amount to cents
     */
    protected function amountToCents(float $amount, string $currency): int
    {
        // Most currencies use 2 decimal places
        // Some use 0 (JPY, KRW) or 3 (KWD, BHD)
        $noDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR', 'HUF','KES'];
        
        if (in_array(strtoupper($currency), $noDecimalCurrencies)) {
            return (int) round($amount);
        }
        
        return (int) round($amount * 100);
    }

    /**
     * Convert cents to amount
     */
    protected function centsToAmount(int $cents, string $currency): float
    {
        $noDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR', 'HUF'];
        
        if (in_array(strtoupper($currency), $noDecimalCurrencies)) {
            return (float) $cents;
        }
        
        return $cents / 100;
    }

    /**
     * Create payment intent via Stripe API
     */
    protected function createPaymentIntent(array $data): array
    {
        $url = 'https://api.stripe.com/v1/payment_intents';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true) ?? [];
        
        \Log::info('Stripe createPaymentIntent response', [
            'http_code' => $httpCode,
            'has_id' => isset($decoded['id']),
            'status' => $decoded['status'] ?? 'no status',
            'client_secret' => isset($decoded['client_secret']) ? 'present' : 'missing',
            'error' => $decoded['error'] ?? null
        ]);
        
        return $decoded;
    }

    /**
     * Retrieve payment intent
     */
    protected function retrievePaymentIntent(string $paymentIntentId): array
    {
        $url = 'https://api.stripe.com/v1/payment_intents/' . $paymentIntentId;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * Create refund
     */
    protected function createRefund(string $paymentIntentId, array $data): array
    {
        $url = 'https://api.stripe.com/v1/refunds';
        $data['payment_intent'] = $paymentIntentId;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}