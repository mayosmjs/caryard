<?php namespace Majos\Sellers\Components;

use Cms\Classes\ComponentBase;
use Majos\Sellers\Models\SellerProfile;
use Majos\Sellers\Models\SubscriptionPlan;
use Majos\Sellers\Models\Settings;
use Majos\Sellers\Classes\SubscriptionService;
use Auth;
use Redirect;

/**
 * SubscriptionManager Component
 * Allows sellers to manage their subscription
 */
class SubscriptionManager extends ComponentBase
{
    public $seller;
    public $subscription;
    public $currentPlan;
    public $availablePlans;
    public $subscriptionService;
    public $transactions;

    public function componentDetails()
    {
        return [
            'name' => 'Subscription Manager',
            'description' => 'Manage seller subscription and view available plans',
        ];
    }

    public function defineProperties()
    {
        return [
            'initialPeriodDays' => [
                'title' => 'Initial Period Days',
                'description' => 'Number of days for initial subscription period',
                'type' => 'number',
                'default' => 30,
            ],
            'providers' => [
                'title' => 'Payment Providers',
                'description' => 'Available payment providers (JSON format)',
                'type' => 'text',
                'default' => '{"stripe":"Stripe ","mpesa":"M-Pesa","paypal":"PayPal"}',
            ],
        ];
    }

    public function onRun()
    {
        // Get current user
        $user = Auth::getUser();
        if (!$user) {
            return Redirect::to('/login');
        }

        // Get seller profile
        $this->seller = SellerProfile::where('user_id', $user->id)->first();
        
        if (!$this->seller) {
            // Redirect to become seller page if no profile exists
            return Redirect::to('/seller');
        }

        // Initialize subscription service
        $this->subscriptionService = new SubscriptionService();

        // Get current subscription
        $this->subscription = $this->subscriptionService->getActiveSubscription($this->seller);
        
        // Get current plan
        $this->currentPlan = $this->subscriptionService->getCurrentPlan($this->seller);
        
        // Get available plans
        $this->availablePlans = SubscriptionPlan::active()->get();
        
        // Get transaction history for this seller
        $subscriptionIds = \Majos\Sellers\Models\SellerSubscription::where('seller_id', $this->seller->id)->pluck('id');
        $this->transactions = \Majos\Sellers\Models\SubscriptionTransaction::whereIn('subscription_id', $subscriptionIds)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            

        // Get available payment providers - filter by backend settings
        $providersJson = $this->property('providers');
        $allProviders = [];
        if ($providersJson) {
            $decoded = json_decode($providersJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $allProviders = $decoded;
            }
        }
        // Fallback to default if empty
        if (empty($allProviders)) {
            $allProviders = ['stripe' => 'Stripe', 'mpesa' => 'M-Pesa', 'paypal' => 'PayPal'];
        }
        
        // Filter providers based on backend settings (toggles)
        $settings = Settings::instance();
        $providers = [];
        $providerLogos = [];
        
        foreach ($allProviders as $key => $name) {
            $isEnabled = false;
            $logo = null;
            switch ($key) {
                case 'stripe':
                    $isEnabled = $settings->isStripeEnabled();
                    $logo = $settings->stripe_logo;
                    break;
                case 'mpesa':
                    $isEnabled = $settings->isMpesaEnabled();
                    $logo = $settings->mpesa_logo;
                    break;
                case 'paypal':
                    $isEnabled = $settings->isPayPalEnabled();
                    $logo = $settings->paypal_logo;
                    break;
                default:
                    // Unknown provider - include it (safe fallback)
                    $isEnabled = true;
            }
            
            if ($isEnabled) {
                $providers[$key] = $name;
                // Get logo URL if exists
                if ($logo && $logo instanceof \System\Models\File) {
                    $providerLogos[$key] = $logo->getPath();
                }
            }
        }
        
        $this->page['providers'] = $providers;
        $this->page['transactions'] = $this->transactions;
        $this->page['providerLogos'] = $providerLogos;
        $this->page['subscription'] = $this->subscription;
        $this->page['currentPlan'] = $this->currentPlan;
        $this->page['availablePlans'] = $this->availablePlans;
        $this->page['seller'] = $this->seller;
        $this->page['trialUsed'] = $this->subscriptionService->hasUsedTrial($this->seller);
        $this->page['freeUsed'] = $this->subscriptionService->hasUsedFreePlan($this->seller);
        $this->page['tenantName'] = $this->seller->tenant ? $this->seller->tenant->name : '';
        
        // Check if already has active subscription
        $this->page['hasActiveSubscription'] = $this->subscription ? $this->subscription->isActive() : false;

        // Get Stripe Public Key for the frontend
        $stripeConfig = \Majos\Sellers\Classes\Payments\PaymentFactory::getSettingsConfig('stripe');
        $this->page['stripePubKey'] = $stripeConfig['publishable_key'] ?? '';
        
        // Add assets for payment handling
        $this->addJs('https://js.stripe.com/v3/');
        $this->addCss('/plugins/majos/sellers/assets/css/subscription.css?v=4');
        $this->addJs('/plugins/majos/sellers/assets/js/subscription.js?v=4');
    }

    /**
     * Get subscription status for display
     */
    public function getSubscriptionStatus()
    {
        if (!$this->subscription) {
            return [
                'status' => 'no_subscription',
                'message' => 'No active subscription',
                'can_add_vehicle' => false,
            ];
        }

        $isActive = $this->subscription->isActive();
        $remainingDays = $this->subscription->getRemainingDays();

        return [
            'status' => $this->subscription->status,
            'is_active' => $isActive,
            'remaining_days' => $remainingDays,
            'expires_at' => $this->subscription->expires_at->format('M d, Y'),
            'can_add_vehicle' => $isActive,
            'vehicle_limit' => $this->subscription->getVehicleLimit(),
            'has_unlimited' => $this->subscription->hasUnlimitedVehicles(),
        ];
    }

    /**
     * Start trial subscription
     */
    public function onStartTrial()
    {
        // Initialize service if not already done
        if (!$this->subscriptionService) {
            $this->subscriptionService = new SubscriptionService();
        }
        
        // Get seller if not already loaded
        if (!$this->seller) {
            $user = Auth::getUser();
            if (!$user) {
                \Flash::error('Please login to start a trial.');
                return Redirect::back();
            }
            $this->seller = SellerProfile::where('user_id', $user->id)->first();
            if (!$this->seller) {
                \Flash::error('Please complete your seller profile first.');
                return Redirect::to('/seller');
            }
        }
        
        try {
            $result = $this->subscriptionService->startTrial($this->seller);
            
            if ($result) {
                \Flash::success('Trial subscription started! You can now list up to 2 vehicles.');
                return Redirect::to('/account/subscription');
            }
        } catch (\Exception $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return Redirect::back();
    }

    /**
     * Subscribe to a plan
     */
    public function onSubscribe()
    {
        // Initialize service if not already done
        if (!$this->subscriptionService) {
            $this->subscriptionService = new SubscriptionService();
        }
        
        // Get seller if not already loaded
        if (!$this->seller) {
            $user = Auth::getUser();
            if (!$user) {
                \Flash::error('Please login to subscribe.');
                return Redirect::back();
            }
            $this->seller = SellerProfile::where('user_id', $user->id)->first();
            if (!$this->seller) {
                \Flash::error('Please complete your seller profile first.');
                return Redirect::to('/seller');
            }
        }
        
        // Check if already has active subscription
        $existingSubscription = $this->subscriptionService->getActiveSubscription($this->seller);
        if ($existingSubscription && $existingSubscription->isActive()) {
            \Flash::error('You already have an active subscription. Please manage or renew it from your dashboard.');
            return Redirect::to('/account/subscription');
        }
        
        $planId = post('plan_id');
        $billingCycle = post('billing_cycle', 'monthly');
        $provider = post('provider');
        
        // Validate provider is enabled in settings (security check)
        // Only validate if a known payment provider is specified
        if (in_array($provider, ['stripe', 'mpesa', 'paypal'], true)) {
            $settings = Settings::instance();
            $isEnabled = false;
            switch ($provider) {
                case 'stripe':
                    $isEnabled = $settings->isStripeEnabled();
                    break;
                case 'mpesa':
                    $isEnabled = $settings->isMpesaEnabled();
                    break;
                case 'paypal':
                    $isEnabled = $settings->isPayPalEnabled();
                    break;
            }
            
            if (!$isEnabled) {
                // For M-Pesa JS handler compatibility
                if ($provider === 'mpesa') {
                    return [
                        'success' => false,
                        'message' => 'This payment method is currently unavailable.'
                    ];
                }
                throw new \ApplicationException('This payment method is currently unavailable.');
            }
        }
        
        // For M-Pesa, get phone number - keep provider as-is (can be null for free plans)
        $paymentParams = [
            'provider' => $provider,
            'return_url' => $this->pageUrl('subscription/payment/return'),
        ];
        
        // Add phone for M-Pesa
        if ($provider === 'mpesa') {
            $paymentParams['phone'] = post('phone');
        }

        $result = $this->subscriptionService->subscribe(
            $this->seller,
            $planId,
            $billingCycle,
            $paymentParams
        );

        if (!$result->success) {
            // For M-Pesa JS handler compatibility
            if ($provider === 'mpesa') {
                return [
                    'success' => false,
                    'message' => $result->message
                ];
            }
            throw new \ApplicationException($result->message);
        }

        // For M-Pesa, return JSON for AJAX handling
        if ($provider === 'mpesa') {
            return [
                'success' => true,
                'message' => $result->message,
                'transaction_id' => $result->transactionId,
                'data' => $result->data ?? [],
            ];
        }

        // For Stripe/PayPal, check if we should show modal (client_secret present)
        if (in_array($provider, ['stripe', 'paypal'])) {
            // If there's a client_secret, the frontend will show the payment modal
            if (!empty($result->data['client_secret'])) {
                return [
                    'success' => true,
                    'message' => $result->message,
                    'transaction_id' => $result->transactionId,
                    'data' => $result->data ?? [],
                ];
            }
            
            // Otherwise check for redirect URL (PayPal)
            if (!empty($result->redirectUrl)) {
                return [
                    'success' => true,
                    'message' => $result->message,
                    'transaction_id' => $result->transactionId,
                    'redirectUrl' => $result->redirectUrl,
                ];
            }
        }
        
        \Flash::success($result->message);
        return Redirect::back();
    }

    public function onCheckPaymentStatus()
    {
        $transactionId = post('transaction_id');
        $provider = post('provider');
        
        if (!$transactionId || !$provider) {
            return ['success' => false, 'message' => 'Missing parameters'];
        }
        
        try {
            // For Stripe, the transaction_id is the PaymentIntent ID
            // Let's verify directly with Stripe API
            
            // Get the Stripe config to initialize the provider
            $stripeConfig = \Majos\Sellers\Classes\Payments\PaymentFactory::getSettingsConfig('stripe');
            
            \Log::info('Stripe config check', [
                'has_api_key' => !empty($stripeConfig['api_key']),
                'config_keys' => array_keys($stripeConfig)
            ]);
            
            // If Stripe provider and we have config, use direct verification
            if ($provider === 'stripe' && !empty($stripeConfig['api_key'])) {
                \Log::info('Direct Stripe verification', ['transaction_id' => $transactionId]);
                
                // Create Stripe provider directly
                $stripe = new \Majos\Sellers\Classes\Payments\StripeProvider();
                $initialized = $stripe->initialize($stripeConfig);
                
                \Log::info('Stripe initialize', ['initialized' => $initialized]);
                
                if (!$initialized) {
                    return ['success' => false, 'message' => 'Failed to initialize Stripe'];
                }
                
                $result = $stripe->verifyPayment($transactionId);
                
                \Log::info('Stripe verify result', [
                    'success' => $result->success,
                    'message' => $result->message,
                    'data' => $result->data
                ]);
                
                if ($result->success) {
                    // Find transaction by PaymentIntent ID
                    $transaction = \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();
                    
                    if ($transaction) {
                        \Log::info('Found transaction, updating status', [
                            'current_status' => $transaction->status,
                            'new_status' => \Majos\Sellers\Models\SubscriptionTransaction::STATUS_COMPLETED
                        ]);
                        
                        // Complete the payment
                        $this->subscriptionService = new SubscriptionService();
                        $this->subscriptionService->completePayment($transactionId, $provider, true, $result->data);
                        
                        return [
                            'success' => true,
                            'status' => 'completed',
                            'message' => 'Payment successful!',
                        ];
                    } else {
                        \Log::error('Transaction not found for PaymentIntent: ' . $transactionId);
                    }
                }
                
                // If verification failed but payment actually succeeded (race condition)
                // Check if transaction exists and is still pending
                $transaction = \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();
                if ($transaction && $transaction->status === \Majos\Sellers\Models\SubscriptionTransaction::STATUS_PENDING) {
                    // Check the actual status from result data
                    if (isset($result->data['status']) && $result->data['status'] === 'succeeded') {
                        $this->subscriptionService = new SubscriptionService();
                        $this->subscriptionService->completePayment($transactionId, $provider, true, $result->data);
                        
                        return [
                            'success' => true,
                            'status' => 'completed',
                            'message' => 'Payment successful!',
                        ];
                    }
                }
                
                // Verification failed — card declined, requires new payment method, etc.
                // verifyPayment() already called persistStripeDetails() which saved the
                // payer/card/decline info into metadata['stripe']. Here we also write
                // a last_check entry and, for terminal states, update the status.
                $txnFail = \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();
                if ($txnFail && $txnFail->status !== \Majos\Sellers\Models\SubscriptionTransaction::STATUS_COMPLETED) {
                    $stripeStatus = $result->data['status'] ?? null;
                    $stripeData   = $result->data['stripe'] ?? [];

                    // Terminal Stripe states — the payment cannot recover without user action
                    if (in_array($stripeStatus, ['requires_payment_method', 'canceled'])) {
                        $txnFail->status = \Majos\Sellers\Models\SubscriptionTransaction::STATUS_FAILED;
                    }

                    $meta = $txnFail->metadata ?? [];
                    $meta['last_check'] = [
                        'checked_at'    => now()->toIso8601String(),
                        'result'        => 'failed',
                        'message'       => $result->message,
                        'stripe_status' => $stripeStatus,
                        'failure_code'  => $stripeData['failure_code']  ?? null,
                        'decline_code'  => $stripeData['decline_code']  ?? null,
                        'failure_type'  => $stripeData['failure_type']  ?? null,
                        'card_last4'    => $stripeData['card_last4']    ?? null,
                        'card_brand'    => $stripeData['card_brand']    ?? null,
                    ];
                    $txnFail->metadata = $meta;
                    $txnFail->save();
                }

                return [
                    'success' => false,
                    'message' => isset($result) ? $result->message : 'Payment verification failed',
                ];
            }

            // ── PayPal: Failure handling ──
            if (isset($result) && !$result->success && $provider === 'paypal') {
                $txnFail = \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();
                if ($txnFail && $txnFail->status !== \Majos\Sellers\Models\SubscriptionTransaction::STATUS_COMPLETED) {
                    $paypalData = $result->data['paypal'] ?? [];
                    
                    $meta = $txnFail->metadata ?? [];
                    $meta['last_check'] = [
                        'checked_at'     => now()->toIso8601String(),
                        'result'         => 'failed',
                        'message'        => $result->message,
                        'order_status'   => $paypalData['order_status']   ?? null,
                        'capture_status' => $paypalData['capture_status'] ?? null,
                        'payer_email'    => $paypalData['payer_email']    ?? null,
                        'capture_id'     => $paypalData['capture_id']     ?? null,
                    ];
                    $txnFail->metadata = $meta;
                    $txnFail->save();
                }

                return [
                    'success' => false,
                    'message' => $result->message,
                ];
            }
            
            // Original logic for other providers
            // Check local database first (Webhook might have completed it already)
            $transaction = \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();

            // ── M-Pesa: callback may have swapped transaction_id from CheckoutRequestID ──
            // to the mpesaReceiptNumber. Use findByCheckoutRequestId() so we still find
            // the completed record even though the primary ID changed.
            if (!$transaction && $provider === 'mpesa') {
                $transaction = \Majos\Sellers\Models\SubscriptionTransaction::findByCheckoutRequestId($transactionId);
            }

            \Log::info('Checking payment status', [
                'transaction_id'     => $transactionId,
                'provider'           => $provider,
                'transaction_found'  => !!$transaction,
                'transaction_status' => $transaction->status ?? 'not found',
            ]);

            // Short-circuit: already completed (callback or previous poll processed it)
            if ($transaction && $transaction->status === \Majos\Sellers\Models\SubscriptionTransaction::STATUS_COMPLETED) {
                return [
                    'success' => true,
                    'status'  => 'completed',
                    'message' => 'Payment confirmed!',
                ];
            }

            $paymentProvider = \Majos\Sellers\Classes\Payments\PaymentFactory::makeFromSettings($provider);
            $result          = $paymentProvider->verifyPayment($transactionId);

            \Log::info('Payment verification result', [
                'success' => $result->success,
                'message' => $result->message,
                'data'    => $result->data,
            ]);

            if ($result->success) {
                // Payment confirmed by provider — activate subscription
                $this->subscriptionService = new SubscriptionService();
                $this->subscriptionService->completePayment($transactionId, $provider, true, $result->data);

                return [
                    'success' => true,
                    'status'  => 'completed',
                    'message' => 'Payment successful!',
                ];
            }

            // ── Pending: transient API error / rate-limit / still queued ────────────────
            if (isset($result->data['is_pending']) && $result->data['is_pending'] === true) {
                return [
                    'success' => true,
                    'status'  => 'pending',
                    'message' => $result->message,
                ];
            }

            // ── Failed result from provider ──────────────────────────────────────────────
            if (isset($result->data['is_failed']) && $result->data['is_failed'] === true) {

                // For M-Pesa, only treat as a DEFINITIVE failure when Safaricom returns a
                // result code that unambiguously means the user explicitly rejected the request.
                // Codes:  1    = Insufficient funds
                //         17   = Cancelled by user (STK popup dismissed)
                //         1032 = Request cancelled by user
                //         2001 = Wrong PIN (exceeded retries)
                // Any other code (sandbox quirks, timing after authorization, "already processed")
                // is left as 'pending' so the UI keeps polling rather than showing a false failure.
                if ($provider === 'mpesa') {
                    $definitiveFailureCodes = [1, 17, 1032, 2001];
                    $resultCode             = $result->data['result_code'] ?? null;

                    if (!in_array($resultCode, $definitiveFailureCodes)) {
                        \Log::info('M-Pesa ambiguous ResultCode — keeping as pending', [
                            'result_code'    => $resultCode,
                            'result_message' => $result->message,
                            'transaction_id' => $transactionId,
                        ]);
                        return [
                            'success' => true,
                            'status'  => 'pending',
                            'message' => 'Verifying payment status...',
                        ];
                    }
                }

                // Definitive failure — update DB status and notify the UI
                // Re-use the already-fetched $transaction (may be null if not found by either method)
                $failTxn = $transaction
                    ?? \Majos\Sellers\Models\SubscriptionTransaction::where('transaction_id', $transactionId)->first();

                if ($failTxn && $failTxn->status !== \Majos\Sellers\Models\SubscriptionTransaction::STATUS_COMPLETED) {
                    $failTxn->status = \Majos\Sellers\Models\SubscriptionTransaction::STATUS_FAILED;
                    $metadata = $failTxn->metadata ?? [];
                    $metadata['failure_reason'] = $result->message;
                    $metadata['raw_response']   = $result->data['raw'] ?? null;
                    $failTxn->metadata = $metadata;
                    $failTxn->save();
                }

                return [
                    'success' => true,
                    'status'  => 'failed',
                    'message' => $result->message,
                ];
            }

            return [
                'success' => true,
                'status'  => 'pending',
                'message' => $result->message,
            ];
        } catch (\Exception $e) {
            \Log::error('Check payment status error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function onCancelSubscription()
    {
        // Initialize service if not already done
        if (!$this->subscriptionService) {
            $this->subscriptionService = new SubscriptionService();
        }
        
        // Get seller if not already loaded
        if (!$this->seller) {
            $user = Auth::getUser();
            if (!$user) {
                \Flash::error('Please login to cancel subscription.');
                return Redirect::back();
            }
            $this->seller = SellerProfile::where('user_id', $user->id)->first();
            if (!$this->seller) {
                \Flash::error('Please complete your seller profile first.');
                return Redirect::to('/seller');
            }
        }
        
        try {
            $this->subscriptionService->cancel($this->seller);
            \Flash::success('Subscription cancelled successfully.');
        } catch (\Exception $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return Redirect::back();
    }

    /**
     * Get available payment providers based on settings
     */
    public function getPaymentProviders()
    {
        $settings = \Majos\Sellers\Models\Settings::instance();
        return $settings->getAvailableProviders();
    }

    /**
     * Download transactions as CSV
     */
    protected function downloadTransactionsCsv()
    {
        $transactions = $this->transactions;
        
        $csv = "Date,Provider,Amount,Status,Transaction ID\n";
        
        foreach ($transactions as $txn) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $txn->created_at->format('M d, Y H:i'),
                $txn->providerDisplayName,
                $txn->formattedAmount,
                $txn->status,
                $txn->transaction_id ?? '-'
            );
        }
        
        $filename = 'transactions_' . date('Y-m-d') . '.csv';
        
        return \Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /**
     * AJAX handler to download transactions as CSV
     */
    public function onDownloadTransactions()
    {
        $user = \Auth::getUser();
        if (!$user) {
            return ['error' => 'Unauthorized'];
        }

        $seller = SellerProfile::where('user_id', $user->id)->first();
        if (!$seller) {
            return ['error' => 'No subscription'];
        }

        $subscriptionIds = \Majos\Sellers\Models\SellerSubscription::where('seller_id', $seller->id)->pluck('id');
        $transactions = \Majos\Sellers\Models\SubscriptionTransaction::whereIn('subscription_id', $subscriptionIds)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($transactions->isEmpty()) {
            return ['error' => 'No transactions'];
        }

        $csv = "Date,Provider,Amount,Status,Transaction ID\n";
        
        foreach ($transactions as $txn) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $txn->created_at->format('M d, Y H:i'),
                $txn->providerDisplayName,
                $txn->formattedAmount,
                $txn->status,
                $txn->transaction_id ?? '-'
            );
        }

        // Return as base64 for JavaScript download
        return [
            'csv' => base64_encode($csv),
            'filename' => 'transactions_' . date('Y-m-d') . '.csv'
        ];
    }
}