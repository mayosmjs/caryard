<?php namespace Majos\Caryard;

use System\Classes\PluginBase;
use RainLab\User\Models\User;
use Majos\Caryard\Models\SellerLoanSettings as LoanSetting;

/**
 * Plugin class
 */
class Plugin extends PluginBase
{
    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        $this->registerConsoleCommand('caryard_resettables', \Majos\Caryard\Console\ResetCaryardTables::class);
        $this->registerConsoleCommand('caryard_clearhistory', \Majos\Caryard\Console\ClearMigrationHistory::class);
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return [
            'Majos\Caryard\Components\VehicleList'     => 'vehicleList',
            'Majos\Caryard\Components\VehicleDetail'   => 'vehicleDetail',
            'Majos\Caryard\Components\TenantRedirect'  => 'tenantRedirect',
            'Majos\Caryard\Components\SellerVehicleManager' => 'sellerVehicleManager',
            'Majos\Caryard\Components\Loan' => 'loan',
            'Majos\Caryard\Components\BrandFilter' => 'brandFilter',
        ];
    }

    public $require = ['RainLab.User','Majos.Sellers'];

    /**
     * registerNavigation for the backend
     */
    public function registerNavigation()
    {
        return [
            'caryard' => [
                'label'       => 'Caryard',
                'url'         => \Backend::url('majos/caryard/vehicles'),
                'icon'        => 'icon-car',
                'permissions' => ['majos.caryard.*'],
                'order'       => 500,
                'sideMenu' => [
                    'vehicles' => [
                        'label'       => 'Vehicles',
                        'icon'        => 'icon-car',
                        'url'         => \Backend::url('majos/caryard/vehicles'),
                        'permissions' => ['majos.caryard.access_vehicles'],
                    ],
                    'advertisements' => [
                        'label'       => 'Advertisements',
                        'icon'        => 'icon-picture-o',
                        'url'         => \Backend::url('majos/caryard/advertisements'),
                        'permissions' => ['majos.caryard.access_advertisements'],
                    ],
                    'brands' => [
                        'label'       => 'Brands',
                        'icon'        => 'icon-star',
                        'url'         => \Backend::url('majos/caryard/brands'),
                        'permissions' => ['majos.caryard.access_brands'],
                    ],
                    'vehiclemodels' => [
                        'label'       => 'Vehicle Models',
                        'icon'        => 'icon-tags',
                        'url'         => \Backend::url('majos/caryard/vehiclemodels'),
                        'permissions' => ['majos.caryard.access_vehicle_models'],
                    ],
                    'bodytypes' => [
                        'label'       => 'Body Types',
                        'icon'        => 'icon-truck',
                        'url'         => \Backend::url('majos/caryard/bodytypes'),
                        'permissions' => ['majos.caryard.access_body_types'],
                    ],
                    'colors' => [
                        'label'       => 'Colors',
                        'icon'        => 'icon-paint-brush',
                        'url'         => \Backend::url('majos/caryard/colors'),
                        'permissions' => ['majos.caryard.access_colors'],
                    ],
                    'conditions' => [
                        'label'       => 'Conditions',
                        'icon'        => 'icon-check-square-o',
                        'url'         => \Backend::url('majos/caryard/conditions'),
                        'permissions' => ['majos.caryard.access_conditions'],
                    ],
                    'drivetypes' => [
                        'label'       => 'Drive Types',
                        'icon'        => 'icon-cogs',
                        'url'         => \Backend::url('majos/caryard/drivetypes'),
                        'permissions' => ['majos.caryard.access_drive_types'],
                    ],
                    'enginecapacities' => [
                        'label'       => 'Engine Capacities',
                        'icon'        => 'icon-tachometer',
                        'url'         => \Backend::url('majos/caryard/enginecapacities'),
                        'permissions' => ['majos.caryard.access_engine_capacities'],
                    ],
                    'fueltypes' => [
                        'label'       => 'Fuel Types',
                        'icon'        => 'icon-filter',
                        'url'         => \Backend::url('majos/caryard/fueltypes'),
                        'permissions' => ['majos.caryard.access_fuel_types'],
                    ],
                    'transmissions' => [
                        'label'       => 'Transmissions',
                        'icon'        => 'icon-exchange',
                        'url'         => \Backend::url('majos/caryard/transmissions'),
                        'permissions' => ['majos.caryard.access_transmissions'],
                    ],
                    'tenants' => [
                        'label'       => 'Tenants (Organizations)',
                        'icon'        => 'icon-building',
                        'url'         => \Backend::url('majos/caryard/tenants'),
                        'permissions' => ['majos.caryard.access_tenants'],
                    ],
                    'divisions' => [
                        'label'       => 'Administrative Divisions',
                        'icon'        => 'icon-sitemap',
                        'url'         => \Backend::url('majos/caryard/administrativedivisions'),
                        'permissions' => ['majos.caryard.access_divisions'],
                    ],
                    'divisiontypes' => [
                        'label'       => 'Division Types',
                        'icon'        => 'icon-list-ol',
                        'url'         => \Backend::url('majos/caryard/divisiontypes'),
                        'permissions' => ['majos.caryard.access_divisions'],
                    ],
                    'settings' => [
                        'label'       => 'Site Settings',
                        'icon'        => 'icon-cog',
                        'url'         => \Backend::url('majos/caryard/settings'),
                        'permissions' => ['majos.caryard.settings'],
                    ],
                    'subscriptionplans' => [
                        'label'       => 'Subscription Plans',
                        'icon'        => 'icon-tags',
                        'url'         => \Backend::url('majos/sellers/subscriptionplans'),
                        'permissions' => ['majos.sellers.manage_plans'],
                    ],
                    'sellersubscriptions' => [
                        'label'       => 'Subscriptions',
                        'icon'        => 'icon-credit-card',
                        'url'         => \Backend::url('majos/sellers/sellersubscriptions'),
                        'permissions' => ['majos.sellers.manage_subscriptions'],
                    ],
                    'subscriptiontransactions' => [
                        'label'       => 'Transactions',
                        'icon'        => 'icon-history',
                        'url'         => \Backend::url('majos/sellers/subscriptiontransactions'),
                        'permissions' => ['majos.sellers.view_transactions'],
                    ],
                ],
            ],
        ];
    }

    /**
     * registerSettings for the backend - Native OctoberCMS Settings
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Site Settings',
                'description' => 'Manage global site settings including contact info, social links, and maintenance mode.',
                'icon'        => 'icon-cog',
                'url'         => \Backend::url('majos/caryard/settings'),
                'order'       => 500,
                'permissions' => ['majos.caryard.settings'],
            ],
        ];
    }

    /**
     * registerPermissions for the backend
     */
    public function registerPermissions()
    {
        return [
            'majos.caryard.access_advertisements' => [
                'tab'   => 'Caryard',
                'label' => 'Access Advertisements'
            ],
        ];
    }
}
