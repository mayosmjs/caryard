<?php namespace Majos\Location;

use System\Classes\PluginBase;
use Backend;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Location',
            'description' => 'Geographic management system.',
            'author'      => 'Majos',
            'icon'        => 'icon-globe'
        ];
    }

    public function registerNavigation()
    {
        return [
            'location' => [
                'label'       => 'Locations',
                'url'         => Backend::url('majos/location/countries'),
                'icon'        => 'icon-globe',
                'permissions' => ['majos.location.*'],
                'order'       => 510,
                'sideMenu' => [
                    'countries' => [
                        'label'       => 'Countries',
                        'icon'        => 'icon-flag',
                        'url'         => Backend::url('majos/location/countries'),
                        'permissions' => ['majos.location.access_countries'],
                    ],
                    'provinces' => [
                        'label'       => 'Provinces / States',
                        'icon'        => 'icon-map',
                        'url'         => Backend::url('majos/location/provinces'),
                        'permissions' => ['majos.location.access_provinces'],
                    ],
                    'cities' => [
                        'label'       => 'Cities',
                        'icon'        => 'icon-map-pin',
                        'url'         => Backend::url('majos/location/cities'),
                        'permissions' => ['majos.location.access_cities'],
                    ],
                ]
            ]
        ];
    }
}
