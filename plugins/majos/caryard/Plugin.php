<?php namespace Majos\Caryard;

use System\Classes\PluginBase;

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
        ];
    }

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
                    ]
                ]
            ]
        ];
    }

    /**
     * registerSettings used by the backend.
     */
    public function registerSettings()
    {
    }
}
