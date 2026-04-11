<?php namespace Majos\Sellers;

use System\Classes\PluginBase;
use Backend\Facades\Backend;
use RainLab\User\Models\User as UserModel;
use RainLab\User\Controllers\Users as UsersController;
use Majos\Sellers\Models\SellerProfile;
use Majos\Caryard\Models\SellerLoanSettings as LoanSetting;
use Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Sellers Plugin Information File
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Sellers',
            'description' => 'Seller profile and KYC management with subscription support.',
            'author'      => 'Majos',
            'icon'        => 'icon-users'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register()
    {
        // Register console commands
        $this->registerConsoleCommand('sellers.checkExpired', \Majos\Sellers\Console\CheckExpiredSubscriptions::class);
    }

    /**
     * Register scheduled tasks
     */
    public function registerSchedule($schedule)
    {
        // Run daily to check and expire subscriptions
        $schedule->command('sellers:check-expired-subscriptions')->daily();
    }

    /**
     * registerNavigation method, called when the plugin is registered.
     */
    public function registerNavigation()
    {
        return [
            'sellers' => [
                'label'       => 'Payments',
                'url'         => \Backend::url('majos/sellers/sellerprofiles'),
                'icon'        => 'icon-users',
                'permissions' => ['majos.sellers.*'],
                'order'       => 600,
                'sideMenu' => [
                    // 'sellerprofiles' => [
                    //     'label'       => 'Seller Profiles',
                    //     'icon'        => 'icon-user',
                    //     'url'         => \Backend::url('majos/sellers/sellerprofiles'),
                    //     'permissions' => ['majos.sellers.access_profiles'],
                    // ],
                    'sellersubscriptions' => [
                        'label'       => 'Subscriptions',
                        'icon'        => 'icon-credit-card',
                        'url'         => \Backend::url('majos/sellers/sellersubscriptions'),
                        'permissions' => ['majos.sellers.access_subscriptions'],
                    ],
                    'subscriptionplans' => [
                        'label'       => 'Plans',
                        'icon'        => 'icon-list',
                        'url'         => \Backend::url('majos/sellers/subscriptionplans'),
                        'permissions' => ['majos.sellers.access_plans'],
                    ],
                    'subscriptiontransactions' => [
                        'label'       => 'Transactions',
                        'icon'        => 'icon-exchange',
                        'url'         => \Backend::url('majos/sellers/subscriptiontransactions'),
                        'permissions' => ['majos.sellers.access_transactions'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Register components
     */
    public function registerComponents()
    {
        return [
            'Majos\Sellers\Components\SubscriptionManager' => 'SubscriptionManager',
        ];
    }

    /**
     * Register settings for the plugin
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Subscription Settings',
                'description' => 'Manage payment providers and subscription settings.',
                'category'    => 'Caryard',
                'icon'        => 'icon-cog',
                'class'       => 'Majos\\Sellers\\Models\\Settings',
                'order'       => 500,
                'keywords'    => 'subscription payment mpesa stripe paypal'
            ]
        ];
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        
        // Extend the User model
        UserModel::extend(function($model) {
            $model->hasOne['seller_profile'] = [
                'Majos\Sellers\Models\SellerProfile',
                'key' => 'user_id',
                'delete' => true
            ];

            $model->hasOne['loan_setting'] = [
                LoanSetting::class,
                'key' => 'user_id'
            ];

            // Dynamic option method for division dropdown on nested seller_profile
            $model->addDynamicMethod('getSellerProfileDivisionIdOptions', function() {
                $formData = post();
                $tenantId = array_get($formData, 'User.seller_profile.tenant_id')
                         ?: array_get($formData, 'seller_profile.tenant_id');

                if (!$tenantId) return [];

                return \Majos\Caryard\Models\AdministrativeDivision::where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->orderBy('level')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(function ($div) {
                        $prefix = str_repeat('— ', $div->level - 1);
                        return [$div->id => $prefix . $div->name];
                    })
                    ->toArray();
            });
        });

        // Listen for frontend profile updates to save seller_profile data
        Event::listen('rainlab.user.update', function($user, $data) {
            if (isset($data['seller_profile']) && is_array($data['seller_profile'])) {
                if ($user->seller_profile) {
                    $user->seller_profile->fill($data['seller_profile']);
                    $user->seller_profile->save();
                } else {
                    // Temporarily pause tenant validation if we must create it, 
                    // though typically the tenant is assigned during backend creation
                    $profile = new SellerProfile;
                    $profile->user_id = $user->id;
                    $profile->fill($data['seller_profile']);
                    try {
                        $profile->save();
                    } catch (\Exception $ex) {
                        // If it fails validation (e.g., missing tenant_id), we gracefully ignore or log
                        // as the user hasn't been assigned a tenant yet.
                    }
                }
            }
        });

        // Extend the User backend form
        Event::listen('backend.form.extendFields', function($widget) {
            if (!$widget->getController() instanceof UsersController) {
                return;
            }

            if (!$widget->model instanceof UserModel) {
                return;
            }

            // Ensure the relation exists for data binding
            if (!$widget->model->seller_profile) {
                $profile = new SellerProfile;
                $profile->user_id = $widget->model->id;
                $widget->model->setRelation('seller_profile', $profile);
            }

            $widget->addTabFields([
                'seller_profile[tenant]' => [
                    'label' => 'Tenant (Organization)',
                    'type' => 'relation',
                    'nameFrom' => 'name',
                    'tab' => 'Seller Profile',
                    'span' => 'auto',
                    'emptyOption' => '-- Select Tenant --'
                ],
                'seller_profile[company_name]' => [
                    'label' => 'Company Name',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'seller_profile[phone_number]' => [
                    'label' => 'Phone Number',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'seller_profile[identification_type]' => [
                    'label' => 'ID Type',
                    'type' => 'dropdown',
                    'options' => [
                        'national_id' => 'National ID',
                        'passport' => 'Passport',
                        'dl' => 'Driving License'
                    ],
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'seller_profile[identification_number]' => [
                    'label' => 'ID Number',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'seller_profile[tax_id]' => [
                    'label' => 'Tax ID',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'seller_profile[address]' => [
                    'label' => 'Address',
                    'type' => 'textarea',
                    'tab' => 'Seller Profile',
                    'size' => 'small'
                ],
                'seller_profile[division_id]' => [
                    'label' => 'Location (Division)',
                    'type' => 'dropdown',
                    'tab' => 'Seller Profile',
                    'span' => 'auto',
                    'dependsOn' => ['seller_profile[tenant]'],
                    'emptyOption' => '-- Select Tenant First --',
                    'comment' => 'Administrative division (e.g. County → Town)'
                ],
                'seller_profile[is_verified_seller]' => [
                    'label' => 'Verified Seller',
                    'type' => 'switch',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
                'subscription_list' => [
                    'label' => 'Subscriptions',
                    'type' => 'partial',
                    'tab' => 'Subscriptions',
                    'path' => '$/majos/sellers/partials/_subscriptions_partial.htm',
                ],
                'transaction_list' => [
                    'label' => 'Transaction History',
                    'type' => 'partial',
                    'tab' => 'Transactions',
                    'path' => '$/majos/sellers/partials/_transactions_partial.htm',
                ],
            ]);
        });
    }
}
