<?php namespace Majos\Sellers\Classes\Payments;

use Majos\Sellers\Models\SellerSubscription;

/**
 * Payment Provider Interface
 * Strategy pattern for multiple payment providers
 */
interface PaymentProviderInterface
{
    /**
     * Initialize the payment provider with configuration
     */
    public function initialize(array $config): bool;

    /**
     * Get provider name
     */
    public function getProviderName(): string;

    /**
     * Create a payment/transaction
     */
    public function createPayment(SellerSubscription $subscription, array $params): PaymentResult;

    /**
     * Verify a payment
     */
    public function verifyPayment(string $transactionId): PaymentResult;

    /**
     * Process a webhook/callback
     */
    public function processWebhook(array $data): PaymentResult;

    /**
     * Refund a transaction
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResult;

    /**
     * Get supported payment types
     */
    public function getSupportedPaymentTypes(): array;

    /**
     * Validate payment configuration
     */
    public function validateConfig(): bool;
}