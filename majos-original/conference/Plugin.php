<?php namespace Majos\Conference;

use Backend;
use System\Classes\PluginBase;
use Majos\Conference\Models\Settings;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Conference',
            'description' => 'Handles AFRIRPA abstract submissions and review decisions.',
            'author' => 'Majos',
            'icon' => 'icon-file-text-o',
            'homepage' => 'https://afrirpa.eaarp.co.ke',
        ];
    }

    public function registerComponents()
    {
        return [
            \Majos\Conference\Components\Registration::class => 'conferenceRegistration',
            \Majos\Conference\Components\EditSubmission::class => 'conferenceEditSubmission',
        ];
    }

    public function registerNavigation()
    {
        return [
            'conference' => [
                'label' => 'Conference',
                'url' => Backend::url('majos/conference/submissions'),
                'icon' => 'icon-file-text-o',
                'permissions' => ['majos.conference.manage_submissions'],
                'order' => 500,
                'sideMenu' => [
                    'submissions' => [
                        'label' => 'Submissions',
                        'icon' => 'icon-inbox',
                        'url' => Backend::url('majos/conference/submissions'),
                        'permissions' => ['majos.conference.manage_submissions'],
                    ],
                    'settings' => [
                        'label' => 'Settings',
                        'icon' => 'icon-cog',
                        'url' => Backend::url('majos/conference/settings'),
                        'permissions' => ['majos.conference.manage_submissions'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'majos.conference.manage_submissions' => [
                'tab' => 'Conference',
                'label' => 'Manage abstract submissions',
            ],
        ];
    }



    public function registerSettings()
{
    return [
        'settings' => [
            'label'       => 'Conference Settings',
            'description' => 'Manage conference settings.',
            'category'    => 'Conference',
            'icon'        => 'icon-cog',
            'class'       => 'Majos\Conference\Models\Settings',
            'order'       => 500,
            'keywords'    => 'conference settings',
            'permissions' => ['majos.conference.manage_settings'],
        ]
    ];
}

    public function registerFormWidgets()
    {
        return [];
    }

    public function registerListColumnTypes()
    {
        return [];
    }
}
