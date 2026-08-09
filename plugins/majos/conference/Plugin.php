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
            \Majos\Conference\Components\Registration::class  => 'conferenceRegistration',
            \Majos\Conference\Components\EditSubmission::class => 'conferenceEditSubmission',
            \Majos\Conference\Components\ReviewPortal::class   => 'reviewPortal',
            'Majos\Conference\Components\Schedule'     => 'schedule',
            'Majos\Conference\Components\Speakers'     => 'speakers',
            'Majos\Conference\Components\SessionDetail' => 'sessionDetail',
            'Majos\Conference\Components\SessionList'   => 'sessionList',
            'Majos\Conference\Components\Papers'        => 'papers',
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'majos.conference::mail.submission_received'     => 'Submission confirmation sent to the author on successful submission.',
            'majos.conference::mail.review_assigned'         => 'Notification sent to the reviewer when a submission is assigned.',
            'majos.conference::mail.review_decision_submitted' => 'Notification sent to the administrator when the reviewer submits a decision.',
            'majos.conference::mail.decision_notice'         => 'Final acceptance / rejection notice sent to the author.',
        ];
    }

 public function registerNavigation()
{
    return [
        'conference' => [
            'label' => 'Conference',
            'url' => Backend::url('majos/conference/submissions'),
            'icon' => 'icon-calendar',
            'permissions' => ['majos.conference.*'],
            'order' => 500,

            'sideMenu' => [

                'submissions' => [
                    'label'       => 'Submissions',
                    'icon'        => 'icon-inbox',
                    'url'         => Backend::url('majos/conference/submissions'),
                    'permissions' => ['majos.conference.manage_submissions'],
                ],

                'presentation_types' => [
                    'label'       => 'Presentation Types',
                    'icon'        => 'icon-list-alt',
                    'url'         => Backend::url('majos/conference/presentationtypes'),
                    'permissions' => ['majos.conference.manage_settings'],
                ],

                'research_areas' => [
                    'label'       => 'Research Areas',
                    'icon'        => 'icon-sitemap',
                    'url'         => Backend::url('majos/conference/researchareas'),
                    'permissions' => ['majos.conference.manage_settings'],
                ],

                'papers' => [
                    'label' => 'Paper Submissions',
                    'url' => Backend::url('majos/conference/papersubmissions'),
                    'icon' => 'icon-file-text-o',
                    'permissions' => ['majos.conference.manage_papers']
                ],

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

                'committees' => [
                    'label' => 'Committee',
                    'url' => Backend::url('majos/conference/committees'),
                    'icon' => 'icon-users',
                    'permissions' => ['majos.conference.manage_committees']
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

             
            ],
        ],
    ];
}

    public function registerPermissions()
    {
        return [
            'majos.conference.manage_submissions' => [
                'tab'   => 'Conference',
                'label' => 'Manage abstract submissions',
            ],
            'majos.conference.manage_settings' => [
                'tab'   => 'Conference',
                'label' => 'Manage conference settings',
            ],
            'majos.conference.manage_committees' => [
                'tab'   => 'Conference',
                'label' => 'Manage committee members',
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
