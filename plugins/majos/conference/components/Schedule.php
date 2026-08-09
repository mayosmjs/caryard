<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Majos\Conference\Models\ConferenceDay;
use Majos\Conference\Models\Session as SessionModel;
use Majos\Conference\Classes\SpeakerPopupData;
use Carbon\Carbon;

/**
 * Schedule Component
 *
 * Displays the conference schedule with filtering options
 */
class Schedule extends ComponentBase
{
    /**
     * @var array Available tracks for filtering
     */
    protected $tracks = [
        'ai' => 'AI & Machine Learning',
        'cybersecurity' => 'Cybersecurity',
        'energy' => 'Energy & Sustainability',
        'policy' => 'Policy & Regulation',
        'finance' => 'Finance & FinTech',
        'health' => 'Health & Biotech',
        'education' => 'Education & EdTech',
        'other' => 'Other'
    ];

    /**
     * @var array Available session types for filtering
     */
    protected $sessionTypes = [
        'keynote' => 'Keynote',
        'workshop' => 'Workshop',
        'panel' => 'Panel',
        'fireside_chat' => 'Fireside Chat',
        'break' => 'Break',
        'presentation' => 'Presentation',
        'qa' => 'Q&A Session'
    ];

    public function componentDetails()
    {
        return [
            'name' => 'Conference Schedule',
            'description' => 'Displays the event schedule with optional filtering by day, track, and session type'
        ];
    }

    public function defineProperties()
    {
        return [
            'showFilters' => [
                'title' => 'Show Filters',
                'description' => 'Display filter dropdowns',
                'type' => 'checkbox',
                'default' => true
            ],
            'showDayFilter' => [
                'title' => 'Show Day Filter',
                'description' => 'Display day filter dropdown',
                'type' => 'checkbox',
                'default' => true
            ],
            'showTrackFilter' => [
                'title' => 'Show Track Filter',
                'description' => 'Display track filter dropdown',
                'type' => 'checkbox',
                'default' => true
            ],
            'showTypeFilter' => [
                'title' => 'Show Type Filter',
                'description' => 'Display session type filter dropdown',
                'type' => 'checkbox',
                'default' => true
            ],
            'defaultDay' => [
                'title' => 'Default Day',
                'description' => 'Day ID to show initially (0 = all days)',
                'type' => 'dropdown',
                'default' => '0'
            ],
            'defaultTrack' => [
                'title' => 'Default Track',
                'description' => 'Track to filter by initially (empty = all tracks)',
                'type' => 'dropdown',
                'default' => ''
            ],
            'defaultType' => [
                'title' => 'Default Type',
                'description' => 'Session type to filter by initially (empty = all types)',
                'type' => 'dropdown',
                'default' => ''
            ]
        ];
    }

    public function init()
    {
        // Load days for dropdowns
        $this->page['days'] = ConferenceDay::isPublished()
            ->ordered()
            ->get()
            ->pluck('title', 'id')
            ->prepend('All Days', 0)
            ->toArray();

        $this->page['tracks'] = $this->tracks;
        $this->page['sessionTypes'] = $this->sessionTypes;
    }

    public function onRun()
    {
        $this->page['schedule'] = $this->loadSchedule();
        $this->page['currentFilters'] = $this->getCurrentFilters();
    }

    public function getSpeakerPopupData($speaker)
    {
        return SpeakerPopupData::encode($speaker);
    }

    public function getSpeakerImageUrl($speaker)
    {
        return SpeakerPopupData::getImageUrl($speaker);
    }

    /**
     * Get current filter values
     */
    protected function getCurrentFilters()
    {
        return [
            'day' => $this->property('defaultDay', 0),
            'track' => $this->property('defaultTrack', ''),
            'type' => $this->property('defaultType', '')
        ];
    }

    /**
     * Load schedule data with applied filters
     */
    protected function loadSchedule()
    {
        $dayId = $this->property('defaultDay', 0);
        $track = $this->property('defaultTrack', '');
        $type =  $this->property('defaultType', '');

        $daysQuery = ConferenceDay::isPublished()->ordered();
        if ($dayId && $dayId != 0) {
            $daysQuery->where('id', $dayId);
        }

        $days = $daysQuery->get();

        $query = SessionModel::with(['day', 'venue', 'speakers'])
            ->isPublished()
            ->ordered();

        // Apply day filter
        if ($dayId && $dayId != 0) {
            $query->onDay($dayId);
        }

        // Apply track filter
        if ($track) {
            $query->byTrack($track);
        }

        // Apply type filter
        if ($type) {
            $query->byType($type);
        }

        $sessions = $query->get();

        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day->id] = [
                'day' => $day,
                'sessions' => []
            ];
        }

        foreach ($sessions as $session) {
            $sessionDayId = $session->conference_day_id;

            if (!isset($schedule[$sessionDayId])) {
                continue;
            }

            $schedule[$sessionDayId]['sessions'][] = $session;
        }

        return $schedule;
    }

    /**
     * AJAX handler for filtering
     */
    public function onFilter()
    {
        $day = post('day', 0);
        $track = post('track', '');
        $type = post('type', '');

        $this->setProperty('defaultDay', $day);
        $this->setProperty('defaultTrack', $track);
        $this->setProperty('defaultType', $type);

        $this->page['schedule'] = $this->loadSchedule();
        $this->page['currentFilters'] = [
            'day' => $day,
            'track' => $track,
            'type' => $type
        ];

        return [
            '#schedule-container' => $this->renderPartial('@schedule.htm')
        ];
    }
}
