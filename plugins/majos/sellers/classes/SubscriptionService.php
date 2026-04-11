<?php namespace Majos\Sellers\Classes;

use Majos\Sellers\Models\SellerProfile;
use Majos\Sellers\Models\SellerSubscription;
use Majos\Sellers\Models\SubscriptionPlan;
use Majos\Sellers\Models\SubscriptionTransaction;
use Majos\Sellers\Classes\Payments\PaymentFactory;
use Majos\Sellers\Classes\Payments\PaymentResult;
use Exception;
use Log;

/**
 * Subscription Service
 * Core business logic for subscription management
 */
class SubscriptionService
{
    /**
     * Start a trial for a seller
     */
    public function startTrial(SellerProfile $seller): ?SellerSubscription
    {
        // Check if trial has already been used in the past
        if ($this->hasUsedTrial($seller)) {
            throw new Exception('Trial period has already been used by this account');
        }

        // Check if seller already has an active subscription
        $existingSubscription = $this->getActiveSubscription($seller);
        if ($existingSubscription && $existingSubscription->isActive()) {
            throw new Exception('Seller already has an active subscription');
        }

        // Get trial plan
        $trialPlan = SubscriptionPlan::getTrialPlan();
        if (!$trialPlan) {
            throw new Exception('Trial plan not found');
        }

        // Calculate trial period
        $startedAt = now();
        $expiresAt = $startedAt->addDays($trialPlan->trial_duration_days ?? 14);

        // Create subscription
        $subscription = SellerSubscription::create([
            'seller_id' => $seller->id,
            'plan_id' => $trialPlan->id,
            'status' => SellerSubscription::STATUS_TRIALING,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'auto_renew' => false,
        ]);

        // Record transaction
        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'provider' => null,
            'transaction_id' => 'TRIAL-' . uniqid(),
            'amount' => 0,
            'currency' => 'KES',
            'status' => SubscriptionTransaction::STATUS_COMPLETED,
            'payment_type' => SubscriptionTransaction::TYPE_TRIAL_START,
            'initiated_by' => SubscriptionTransaction::INITIATED_BY_SYSTEM,
        ]);

        return $subscription;
    }

    /**
     * Check if a seller has ever used a trial
     */
    public function hasUsedTrial(SellerProfile $seller): bool
    {
        return SubscriptionTransaction::whereHas('subscription', function($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->where('payment_type', SubscriptionTransaction::TYPE_TRIAL_START)
            ->where('status', SubscriptionTransaction::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * Check if a seller has ever used a free plan
     */
    public function hasUsedFreePlan(SellerProfile $seller): bool
    {
        return SubscriptionTransaction::whereHas('subscription', function($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->where('payment_type', SubscriptionTransaction::TYPE_FREE_START)
            ->where('status', SubscriptionTransaction::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * Subscribe a seller to a plan
     */
    public function subscribe(
        SellerProfile $seller, 
        int $planId, 
        string $billingCycle = 'monthly',
        array $paymentParams = []
    ): PaymentResult {
        // Get the plan
        $plan = SubscriptionPlan::find($planId);
        if (!$plan || !$plan->is_active) {
            return new PaymentResult(false, 'Plan not found or inactive');
        }

        // Calculate price based on billing cycle (needed early for checks)
        $price = $billingCycle === 'annual' ? ($plan->price_annual ?? 0) : ($plan->price_monthly ?? 0);

        // Check if this is a trial plan - if so, ensure trial hasn't been used yet
        if ($plan->tier === 'trial') {
            if ($this->hasUsedTrial($seller)) {
                return new PaymentResult(false, 'Trial period has already been used by this account. You can subscribe to other available plans.');
            }
        }

        // Check if this is a free plan - if so, ensure free plan hasn't been used yet
        if ($price == 0) {
            if ($this->hasUsedFreePlan($seller)) {
                return new PaymentResult(false, 'Free plan has already been used by this account. You can subscribe to other available plans.');
            }
        }

        // Free plan or explicit free provider
        if ($price == 0 || ($paymentParams['provider'] ?? '') === 'free') {
            return $this->activateFreePlan($seller, $plan);
        }

        // Determine provider
        $providerName = $paymentParams['provider'] ?? 'stripe';
        
        // Determine currency based on provider
        $currency = ($providerName === 'mpesa') ? 'KES' : ($paymentParams['currency'] ?? 'USD');
        
        try {
            // Create or get existing subscription
            $subscription = $this->getOrCreateSubscription($seller, $plan);
            
            // Get payment provider
            $provider = PaymentFactory::makeFromSettings($providerName);

            // Prepare payment params
            $paymentParams = array_merge($paymentParams, [
                'amount' => $price,
                'currency' => $currency,
                'description' => "{$plan->name} - {$billingCycle} subscription",
                'return_url' => $paymentParams['return_url'] ?? ($providerName === 'paypal' ? url('/subscription/paypal/return') : url('/subscription/payment/return')),
                'cancel_url' => $providerName === 'paypal' ? url('/subscription/paypal/cancel') : null,
            ]);

            // Create payment
            $result = $provider->createPayment($subscription, $paymentParams);

            return $result;

        } catch (Exception $e) {
            Log::error('Subscription payment failed: ' . $e->getMessage());
            return new PaymentResult(false, $e->getMessage());
        }
    }

    /**
     * Activate a free plan (no payment required)
     */
    protected function activateFreePlan(SellerProfile $seller, SubscriptionPlan $plan): PaymentResult
    {
        $duration = $plan->trial_duration_days ?? 30;
        
        $subscription = $this->getOrCreateSubscription($seller, $plan);
        
        $subscription->update([
            'status' => SellerSubscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addDays($duration),
        ]);

        // Record transaction
        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'provider' => 'free',
            'transaction_id' => 'FREE-' . uniqid(),
            'amount' => 0,
            'currency' => 'USD',
            'status' => SubscriptionTransaction::STATUS_COMPLETED,
            'payment_type' => SubscriptionTransaction::TYPE_FREE_START,
            'initiated_by' => SubscriptionTransaction::INITIATED_BY_SYSTEM,
        ]);

        return new PaymentResult(true, 'Free plan activated', $subscription->id);
    }

    /**
     * Complete payment and activate subscription
     */
    public function completePayment(string $transactionId, string $provider, bool $skipVerification = false, array $paymentData = []): PaymentResult
    {
        try {
            $verificationResult = null;
            
            if (!$skipVerification) {
                // Get provider
                $paymentProvider = PaymentFactory::makeFromSettings($provider);
                
                // Verify payment
                $verificationResult = $paymentProvider->verifyPayment($transactionId);
                
                // Find transaction first to store metadata even on failure
                $transaction = SubscriptionTransaction::where('transaction_id', $transactionId)->first();
                if ($transaction) {
                    // Save verification data to metadata even on failure
                    $metadata = $transaction->metadata ?? [];
                    $metadata['verification_data'] = [
                        'success' => $verificationResult->success,
                        'message' => $verificationResult->message,
                        'data' => $verificationResult->data,
                        'verified_at' => date('Y-m-d H:i:s'),
                        'provider' => $provider,
                    ];
                    $transaction->metadata = $metadata;
                    $transaction->save();
                }
                
                if (!$verificationResult->success) {
                    return $verificationResult;
                }
            }

            // Find transaction
            $transaction = SubscriptionTransaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                Log::error('Transaction not found: ' . $transactionId);
                return new PaymentResult(false, 'Transaction not found');
            }

            Log::info('Found transaction', [
                'transaction_id' => $transactionId,
                'subscription_id' => $transaction->subscription_id,
                'current_status' => $transaction->status
            ]);

            // If already completed, just return success (Idempotency)
            if ($transaction->status === SubscriptionTransaction::STATUS_COMPLETED) {
                return new PaymentResult(true, 'Payment already processed');
            }

            // Save raw payment metadata (from payment provider verification result)
            $metadata = $transaction->metadata ?? [];
            
            // Store verification result data if available
            if ($verificationResult !== null) {
                $metadata['verification_data'] = [
                    'success' => $verificationResult->success,
                    'message' => $verificationResult->message,
                    'data' => $verificationResult->data,
                    'verified_at' => date('Y-m-d H:i:s'),
                ];
            }
            
            // Also store any raw data passed in
            if (!empty($paymentData['raw'])) {
                $metadata['raw_response'] = $paymentData['raw'];
                $metadata['result_desc'] = $paymentData['result_desc'] ?? null;
            }
            
            $transaction->metadata = $metadata;
            $transaction->save();

            // Mark transaction as completed
            $transaction->markAsCompleted($transactionId);

            // Get subscription and activate
            $subscription = $transaction->subscription;
            if (!$subscription) {
                Log::error('Subscription not found for transaction: ' . $transactionId);
                return new PaymentResult(false, 'Subscription not found');
            }

            Log::info('Activating subscription', [
                'subscription_id' => $subscription->id,
                'seller_id' => $subscription->seller_id
            ]);

            $this->activateSubscription($subscription);

            return new PaymentResult(true, 'Subscription activated successfully', $transactionId);

        } catch (Exception $e) {
            Log::error('Payment completion failed: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return new PaymentResult(false, $e->getMessage());
        }
    }

    /**
     * Activate subscription after successful payment
     */
    protected function activateSubscription(SellerSubscription $subscription): void
    {
        $plan = $subscription->plan;
        
        Log::info('Activating subscription details', [
            'subscription_id' => $subscription->id,
            'seller_id' => $subscription->seller_id,
            'plan_id' => $subscription->plan_id,
            'current_status' => $subscription->status
        ]);
        
        // Calculate billing period
        $startedAt = now();
        $duration = 30; // default monthly
        
        if ($subscription->transactions()->completed()->count() > 1) {
            // Renewal - extend from current expiry
            $duration = 30;
            if ($subscription->expires_at && $subscription->expires_at > now()) {
                $startedAt = $subscription->expires_at;
            }
        }
        
        // Check if annual billing based on amount
        $lastTransaction = $subscription->getLatestSuccessfulTransaction();
        if ($lastTransaction && $plan && $lastTransaction->amount >= $plan->price_annual) {
            $duration = 365;
        }

        $subscription->update([
            'status' => SellerSubscription::STATUS_ACTIVE,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addDays($duration),
        ]);
        
        Log::info('Subscription activated', [
            'subscription_id' => $subscription->id,
            'new_status' => $subscription->status,
            'expires_at' => $subscription->expires_at
        ]);
    }

    /**
     * Create subscription manually (admin)
     */
    public function createManualSubscription(
        SellerProfile $seller,
        int $planId,
        int $durationDays,
        array $transactionData = []
    ): SellerSubscription {
        $plan = SubscriptionPlan::findOrFail($planId);
        
        $subscription = SellerSubscription::create([
            'seller_id' => $seller->id,
            'plan_id' => $plan->id,
            'status' => SellerSubscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addDays($durationDays),
            'auto_renew' => false,
            'notes' => $transactionData['notes'] ?? 'Created manually by admin',
        ]);

        // Record manual transaction
        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'provider' => $transactionData['provider'] ?? null,
            'transaction_id' => $transactionData['transaction_id'] ?? 'ADMIN-' . uniqid(),
            'amount' => $transactionData['amount'] ?? 0,
            'currency' => $transactionData['currency'] ?? 'USD',
            'status' => SubscriptionTransaction::STATUS_COMPLETED,
            'payment_type' => SubscriptionTransaction::TYPE_SUBSCRIPTION_CREATE,
            'initiated_by' => SubscriptionTransaction::INITIATED_BY_ADMIN,
            'metadata' => $transactionData['metadata'] ?? [],
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancel(SellerProfile $seller): bool
    {
        $subscription = $this->getActiveSubscription($seller);
        
        if (!$subscription) {
            return false;
        }

        $subscription->update([
            'status' => SellerSubscription::STATUS_CANCELLED,
            'auto_renew' => false,
        ]);

        return true;
    }

    /**
     * Extend a subscription
     */
    public function extend(SellerProfile $seller, int $days): ?SellerSubscription
    {
        $subscription = $this->getActiveSubscription($seller);
        
        if (!$subscription) {
            return null;
        }

        $subscription->extendSubscription($days);

        // Record extension transaction
        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'provider' => null,
            'transaction_id' => 'EXT-' . uniqid(),
            'amount' => 0,
            'currency' => 'USD',
            'status' => SubscriptionTransaction::STATUS_COMPLETED,
            'payment_type' => SubscriptionTransaction::TYPE_SUBSCRIPTION_RENEW,
            'initiated_by' => SubscriptionTransaction::INITIATED_BY_ADMIN,
            'metadata' => ['extension_days' => $days],
        ]);

        return $subscription;
    }

    /**
     * Check and expire expired subscriptions
     */
    public function checkExpiredSubscriptions(): int
    {
        $expiredSubscriptions = SellerSubscription::expiredByDate()
            ->whereIn('status', [SellerSubscription::STATUS_ACTIVE, SellerSubscription::STATUS_TRIALING])
            ->get();

        $count = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->markAsExpired();
            $count++;
        }

        if ($count > 0) {
            Log::info("Expired {$count} subscriptions");
        }

        return $count;
    }

    /**
     * Check if seller can add more vehicles
     */
    public function canAddVehicle(SellerProfile $seller, int $currentVehicleCount = 0): bool
    {
        $subscription = $this->getActiveSubscription($seller);
        
        if (!$subscription) {
            return false;
        }

        return $subscription->canAddVehicle($currentVehicleCount);
    }

    /**
     * Get vehicle limit for seller
     */
    public function getVehicleLimit(SellerProfile $seller): int
    {
        $subscription = $this->getActiveSubscription($seller);
        
        if (!$subscription) {
            return 0;
        }

        return $subscription->getVehicleLimit();
    }

    /**
     * Get active subscription for seller
     */
    public function getActiveSubscription(SellerProfile $seller): ?SellerSubscription
    {
        return $seller->subscriptions()
            ->whereIn('status', [SellerSubscription::STATUS_ACTIVE, SellerSubscription::STATUS_TRIALING])
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    /**
     * Get current plan for seller
     */
    public function getCurrentPlan(SellerProfile $seller): ?SubscriptionPlan
    {
        $subscription = $this->getActiveSubscription($seller);
        
        return $subscription ? $subscription->plan : null;
    }

    /**
     * Process payment webhook
     */
    public function processWebhook(string $provider, array $data): PaymentResult
    {
        try {
            $paymentProvider = PaymentFactory::makeFromSettings($provider);
            return $paymentProvider->processWebhook($data);
        } catch (Exception $e) {
            Log::error("Webhook processing failed for {$provider}: " . $e->getMessage());
            return new PaymentResult(false, $e->getMessage());
        }
    }

    /**
     * Get or create subscription
     */
    protected function getOrCreateSubscription(SellerProfile $seller, SubscriptionPlan $plan): SellerSubscription
    {
        $existingSubscription = $this->getActiveSubscription($seller);
        
        if ($existingSubscription && $existingSubscription->plan_id == $plan->id) {
            return $existingSubscription;
        }

        // Create new subscription
        return SellerSubscription::create([
            'seller_id' => $seller->id,
            'plan_id' => $plan->id,
            'status' => SellerSubscription::STATUS_TRIALING,
            'started_at' => now(),
            'expires_at' => now()->addDays($plan->trial_duration_days ?? 30),
            'auto_renew' => false,
        ]);
    }
}