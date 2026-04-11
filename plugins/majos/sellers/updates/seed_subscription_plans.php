<?php namespace Majos\Sellers\Updates;

use Seeder;
use Majos\Sellers\Models\SubscriptionPlan;
use Majos\Sellers\Models\SellerProfile;
use Majos\Sellers\Models\SellerSubscription;
use RainLab\User\Models\User;

class SeedSubscriptionPlans extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Free Trial',
                'tier' => 'trial',
                'vehicle_limit' => 2,
                'price_monthly' => 0,
                'price_annual' => 0,
                'is_active' => true,
                'features' => json_encode([
                    'List up to 2 vehicles',
                    'Basic vehicle management',
                    'Email support'
                ]),
                'trial_duration_days' => 14,
                'sort_order' => 1
            ],
            [
                'name' => 'Basic Monthly',
                'tier' => 'basic',
                'vehicle_limit' => 10,
                'price_monthly' => 29.99,
                'price_annual' => 299.99,
                'is_active' => true,
                'features' => json_encode([
                    'List up to 10 vehicles',
                    'Full vehicle management',
                    'Analytics dashboard',
                    'Email support'
                ]),
                'trial_duration_days' => 0,
                'sort_order' => 2
            ],
            [
                'name' => 'Premium Monthly',
                'tier' => 'premium',
                'vehicle_limit' => 0, // 0 means unlimited
                'price_monthly' => 79.99,
                'price_annual' => 799.99,
                'is_active' => true,
                'features' => json_encode([
                    'Unlimited vehicle listings',
                    'Priority support',
                    'Advanced analytics',
                    'Featured listings',
                    'API access'
                ]),
                'trial_duration_days' => 0,
                'sort_order' => 3
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }

        // Get the Basic plan for assigning to existing users
        $basicPlan = SubscriptionPlan::where('tier', 'basic')->first();
        $premiumPlan = SubscriptionPlan::where('tier', 'premium')->first();

        // Get users with seller profiles
        $sellerProfiles = SellerProfile::where('is_seller', true)->get();

        foreach ($sellerProfiles as $profile) {
            // Check if user already has a subscription
            $existingSubscription = SellerSubscription::where('seller_id', $profile->id)->first();
            
            if (!$existingSubscription && $basicPlan) {
                // Create an active subscription for the seller
                $subscription = new SellerSubscription;
                $subscription->seller_id = $profile->id;
                $subscription->plan_id = $basicPlan->id;
                $subscription->status = 'active';
                $subscription->started_at = now();
                $subscription->expires_at = now()->addYear();
                $subscription->auto_renew = false;
                $subscription->save();
            }
        }
    }
}
