<?php namespace Majos\Conference;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * @var string Plugin version
     */
    public $version = '1.0.1';

    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Conference',
            'description' => 'Production-ready Event Schedule module for conferences with days, sessions, speakers, venues, and tracks.',
            'author' => 'Majos',
            'icon' => 'icon-calendar',
            'homepage' => 'https://github.com/majos/conference'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        //
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        //
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return [
            'Majos\Conference\Components\Schedule' => 'schedule',
            'Majos\Conference\Components\Speakers' => 'speakers',
            'Majos\Conference\Components\SessionDetail' => 'sessionDetail',
            'Majos\Conference\Components\SessionList' => 'sessionList',
            'Majos\Conference\Components\Papers' => 'papers',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'majos.conference.manage_days' => [
                'tab' => 'Conference',
                'label' => 'Manage Conference Days'
            ],
            'majos.conference.manage_venues' => [
                'tab' => 'Conference',
                'label' => 'Manage Venues'
            ],
            'majos.conference.manage_speakers' => [
                'tab' => 'Conference',
                'label' => 'Manage Speakers'
            ],
            'majos.conference.manage_sessions' => [
                'tab' => 'Conference',
                'label' => 'Manage Sessions'
            ],
            'majos.conference.manage_papers' => [
                'tab' => 'Conference',
                'label' => 'Manage Paper Submissions'
            ],
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'conference' => [
                'label' => 'Conference',
                'url' => Backend::url('majos/conference/sessions'),
                'icon' => 'icon-calendar',
                'permissions' => ['majos.conference.*'],
                'order' => 500,
                'sideMenu' => [
                    'sessions' => [
                        'label' => 'Sessions',
                        'url' => Backend::url('majos/conference/sessions'),
                        'icon' => 'icon-calendar',
                        'permissions' => ['majos.conference.manage_sessions']
                    ],
                    'speakers' => [
                        'label' => 'Speakers',
                        'url' => Backend::url('majos/conference/speakers'),
                        'icon' => 'icon-user',
                        'permissions' => ['majos.conference.manage_speakers']
                    ],
                    'days' => [
                        'label' => 'Days',
                        'url' => Backend::url('majos/conference/conferencedays'),
                        'icon' => 'icon-calendar-o',
                        'permissions' => ['majos.conference.manage_days']
                    ],
                    'venues' => [
                        'label' => 'Venues',
                        'url' => Backend::url('majos/conference/venues'),
                        'icon' => 'icon-map-marker',
                        'permissions' => ['majos.conference.manage_venues']
                    ],
                    'papers' => [
                        'label' => 'Paper Submissions',
                        'url' => Backend::url('majos/conference/papersubmissions'),
                        'icon' => 'icon-file-text-o',
                        'permissions' => ['majos.conference.manage_papers']
                    ]
                ]
            ],
        ];
    }
}
