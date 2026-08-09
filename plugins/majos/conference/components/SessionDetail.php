<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Majos\Conference\Classes\SpeakerPopupData;
use Majos\Conference\Models\Session as SessionModel;
use Carbon\Carbon;

/**
 * SessionDetail Component
 *
 * Displays a single session details
 */
class SessionDetail extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Session Detail',
            'description' => 'Displays details of a single session'
        ];
    }

    public function defineProperties()
    {
        return [
            'sessionSlug' => [
                'title' => 'Session Slug',
                'description' => 'Slug of the session to display',
                'type' => 'string',
                'default' => ''
            ],
            'sessionId' => [
                'title' => 'Session ID',
                'description' => 'ID of the session to display',
                'type' => 'string',
                'default' => ''
            ]
        ];
    }

    public function onRun()
    {
        $session = $this->loadSession();

        if (!$session) {
            $this->setStatusCode(404);
            return $this->controller->run('404');
        }

        $this->page['session'] = $session;
        $this->page['speakers'] = $session->speakers;
        $this->page['day'] = $session->day;
        $this->page['venue'] = $session->venue;
    }

    /**
     * Load session by slug or ID
     */
    protected function loadSession()
    {
        $slug = $this->property('sessionSlug');
        $id = $this->property('sessionId');

        $query = SessionModel::with(['day', 'venue', 'speakers'])
            ->isPublished();

        if ($slug) {
            $query->where('slug', $slug);
        } elseif ($id) {
            $query->where('id', $id);
        } else {
            return null;
        }

        return $query->first();
    }

    /**
     * Check if session is currently happening
     */
    public function isNow($session)
    {
        if (!$session->starts_at || !$session->ends_at) {
            return false;
        }

        $now = Carbon::now();
        return $now->between($session->starts_at, $session->ends_at);
    }

    /**
     * Check if session is upcoming
     */
    public function isUpcoming($session)
    {
        if (!$session->starts_at) {
            return false;
        }

        return Carbon::now()->lt($session->starts_at);
    }

    /**
     * Check if session is past
     */
    public function isPast($session)
    {
        if (!$session->ends_at) {
            return false;
        }

        return Carbon::now()->gt($session->ends_at);
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
