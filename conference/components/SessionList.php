<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Majos\Conference\Classes\SpeakerPopupData;
use Majos\Conference\Models\Session as SessionModel;
use Majos\Conference\Models\Speaker;

/**
 * SessionList Component
 *
 * Displays a list of sessions with filtering options
 */
class SessionList extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Session List',
            'description' => 'Displays a filtered list of sessions'
        ];
    }

    public function defineProperties()
    {
        return [
            'speakerSlug' => [
                'title' => 'Speaker Slug',
                'description' => 'Filter sessions by speaker',
                'type' => 'string',
                'default' => ''
            ],
            'track' => [
                'title' => 'Track',
                'description' => 'Filter by track',
                'type' => 'dropdown',
                'default' => '',
                'options' => [
                    'ai' => 'AI & Machine Learning',
                    'cybersecurity' => 'Cybersecurity',
                    'energy' => 'Energy & Sustainability',
                    'policy' => 'Policy & Regulation',
                    'finance' => 'Finance & FinTech',
                    'health' => 'Health & Biotech',
                    'education' => 'Education & EdTech',
                    'other' => 'Other'
                ]
            ],
            'sessionType' => [
                'title' => 'Session Type',
                'description' => 'Filter by session type',
                'type' => 'dropdown',
                'default' => '',
                'options' => [
                    'keynote' => 'Keynote',
                    'workshop' => 'Workshop',
                    'panel' => 'Panel',
                    'fireside_chat' => 'Fireside Chat',
                    'break' => 'Break',
                    'presentation' => 'Presentation',
                    'qa' => 'Q&A Session'
                ]
            ],
            'dayId' => [
                'title' => 'Day ID',
                'description' => 'Filter by conference day',
                'type' => 'string',
                'default' => ''
            ],
            'limit' => [
                'title' => 'Limit',
                'description' => 'Number of sessions to display',
                'type' => 'string',
                'default' => ''
            ],
            'order' => [
                'title' => 'Order',
                'description' => 'Order sessions by',
                'type' => 'dropdown',
                'default' => 'starts_at',
                'options' => [
                    'starts_at' => 'Start Time',
                    'sort_order' => 'Custom Order',
                    'title' => 'Title'
                ]
            ]
        ];
    }

    public function onRun()
    {
        $this->page['sessions'] = $this->loadSessions();
    }

    /**
     * Load filtered sessions
     */
    protected function loadSessions()
    {
        $query = SessionModel::with(['day', 'venue', 'speakers'])
            ->isPublished()
            ->orderBy('starts_at', 'asc');

        // Filter by speaker
        $speakerSlug = $this->property('speakerSlug');
        if ($speakerSlug) {
            $speaker = Speaker::isPublished()->where('slug', $speakerSlug)->first();
            if ($speaker) {
                $query->whereHas('speakers', function($q) use ($speaker) {
                    $q->where('speaker_id', $speaker->id);
                });
            }
        }

        // Filter by track
        $track = $this->property('track');
        if ($track) {
            $query->byTrack($track);
        }

        // Filter by session type
        $sessionType = $this->property('sessionType');
        if ($sessionType) {
            $query->byType($sessionType);
        }

        // Filter by day
        $dayId = $this->property('dayId');
        if ($dayId) {
            $query->onDay($dayId);
        }

        // Apply limit
        if ($limit = $this->property('limit')) {
            $query->take($limit);
        }

        // Apply custom ordering
        $order = $this->property('order', 'starts_at');
        if ($order === 'sort_order') {
            $query->orderBy('sort_order', 'asc');
        } else {
            $query->orderBy($order, 'asc');
        }

        return $query->get();
    }

    public function getSpeakerPopupData($speaker)
    {
        return SpeakerPopupData::encode($speaker);
    }

    public function getSpeakerImageUrl($speaker)
    {
        return SpeakerPopupData::getImageUrl($speaker);
    }
}
