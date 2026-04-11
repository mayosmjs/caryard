<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Backend\Facades\BackendAuth;
use Majos\Sellers\Models\SellerSubscription;
use Majos\Sellers\Models\SubscriptionPlan;
use Majos\Sellers\Classes\SubscriptionService;
use Flash;
use Redirect;
use Exception;
use Majos\Caryard\Models\SellerLoanSettings;

/**
 * Seller Subscriptions Controller
 */
class SellerSubscriptions extends Controller
{
    public $requiredPermissions = ['majos.sellers.manage_subscriptions'];

    public $implement = [
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    protected $subscriptionService;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Sellers', 'sellers', 'sellersubscriptions');
        $this->subscriptionService = new SubscriptionService();
    }

    /**
     * Store manual subscription
     */
    public function create_onSave()
    {
        $postData = post();
        $data = $postData['Subscription'] ?? $postData;
        
        try {
            $sellerId = $data['seller_id'] ?? null;
            $planId = $data['plan_id'] ?? null;
            
            if (!$sellerId) {
                throw new Exception('Seller is required');
            }
            
            if (!$planId) {
                throw new Exception('Plan is required');
            }
            
            $plan = SubscriptionPlan::findOrFail($planId);
            $durationDays = $data['duration_days'] ?? 30;

            // Get the seller profile
            $seller = \Majos\Sellers\Models\SellerProfile::findOrFail($sellerId);

            $subscription = $this->subscriptionService->createManualSubscription(
                $seller,
                $plan->id,
                $durationDays,
                [
                    'amount' => $data['amount'] ?? 0,
                    'currency' => $data['currency'] ?? 'USD',
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'notes' => $data['notes'] ?? 'Manual subscription',
                ]
            );

            Flash::success('Subscription created successfully');
            return Redirect::to('backend/majos/sellers/sellersubscriptions');

        } catch (Exception $e) {
            Flash::error('Error creating subscription: ' . $e->getMessage());
            return Redirect::back();
        }
    }

    /**
     * Get seller options for dropdown
     */
    public function getSellerOptions()
    {
        return \Majos\Sellers\Models\SellerProfile::all()->mapWithKeys(function($profile) {
            return [$profile->id => $profile->company_name ?? 'Profile ' . $profile->id];
        })->toArray();
    }

    /**
     * Get plan options for dropdown
     */
    public function getPlanOptions()
    {
        return SubscriptionPlan::active()->get()->mapWithKeys(function($plan) {
            $price = $plan->price_monthly > 0 ? '$' . $plan->price_monthly . '/mo' : 'Free';
            return [$plan->id => $plan->name . ' - ' . $price];
        })->toArray();
    }
}