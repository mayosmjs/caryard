<?php namespace Majos\Sellers;

use System\Classes\PluginBase;
use RainLab\User\Models\User as UserModel;
use RainLab\User\Controllers\Users as UsersController;
use Majos\Sellers\Models\SellerProfile;
use Event;

/**
 * Sellers Plugin Information File
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Sellers',
            'description' => 'Seller profile and KYC management.',
            'author'      => 'Majos',
            'icon'        => 'icon-users'
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

            // Robust dynamic option methods for nested relationship fields
            $model->addDynamicMethod('getSellerProfileProvinceIdOptions', function() {
                $formData = post();
                // Check common October CMS post data paths for the nested field
                $countryId = array_get($formData, 'User.seller_profile.country_id') 
                          ?: array_get($formData, 'seller_profile.country_id');

                if (!$countryId) return [];
                return \Majos\Location\Models\Province::where('country_id', $countryId)->lists('name', 'id');
            });

            $model->addDynamicMethod('getSellerProfileCityIdOptions', function() {
                $formData = post();
                $provinceId = array_get($formData, 'User.seller_profile.province_id') 
                           ?: array_get($formData, 'seller_profile.province_id');

                if (!$provinceId) return [];
                return \Majos\Location\Models\City::where('province_id', $provinceId)->lists('name', 'id');
            });
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
                'seller_profile[country_id]' => [
                    'label' => 'Country',
                    'type' => 'dropdown',
                    'tab' => 'Seller Profile',
                    'span' => 'auto',
                    'emptyOption' => '-- Select Country --',
                    'options' => \Majos\Location\Models\Country::lists('name', 'id')
                ],
                'seller_profile[province_id]' => [
                    'label' => 'Province / State',
                    'type' => 'dropdown',
                    'tab' => 'Seller Profile',
                    'span' => 'auto',
                    'dependsOn' => ['seller_profile[country_id]'],
                    'emptyOption' => '-- Select Province --'
                ],
                'seller_profile[city_id]' => [
                    'label' => 'City',
                    'type' => 'dropdown',
                    'tab' => 'Seller Profile',
                    'span' => 'auto',
                    'dependsOn' => ['seller_profile[province_id]'],
                    'emptyOption' => '-- Select City --'
                ],
                'seller_profile[is_verified_seller]' => [
                    'label' => 'Verified Seller',
                    'type' => 'switch',
                    'tab' => 'Seller Profile',
                    'span' => 'auto'
                ],
            ]);
        });
    }
}
