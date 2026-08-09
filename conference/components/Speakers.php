<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Majos\Conference\Models\Speaker;
use Majos\Conference\Models\Session;
use Majos\Conference\Classes\SpeakerPopupData;
use Carbon\Carbon;

/**
 * Speakers Component
 *
 * Displays speaker profiles with optional filtering
 */
class Speakers extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Conference Speakers',
            'description' => 'Displays speaker profiles with optional filtering and detail view'
        ];
    }

    public function defineProperties()
    {
        return [
            'showFeaturedOnly' => [
                'title' => 'Show Featured Only',
                'description' => 'Only display featured speakers',
                'type' => 'checkbox',
                'default' => false
            ],
            'speakerSlug' => [
                'title' => 'Speaker Slug',
                'description' => 'Slug of a specific speaker to show (for detail page)',
                'type' => 'string',
                'default' => ''
            ],
            'limit' => [
                'title' => 'Limit',
                'description' => 'Number of speakers to display',
                'type' => 'string',
                'default' => ''
            ],
            'order' => [
                'title' => 'Order',
                'description' => 'Order speakers by',
                'type' => 'dropdown',
                'default' => 'full_name',
                'options' => [
                    'full_name' => 'Name',
                    'created_at' => 'Date Added',
                    'company' => 'Company'
                ]
            ]
        ];
    }

    public function onRun()
    {
        $speakerSlug = $this->property('speakerSlug');

        if ($speakerSlug) {
            // Single speaker detail view
            $this->page['speaker'] = $this->loadSpeaker($speakerSlug);
            $this->page['sessions'] = $this->loadSpeakerSessions($speakerSlug);
        } else {
            // List view
            $this->page['speakers'] = $this->loadSpeakers();
        }
    }

    /**
     * Load speaker by slug
     */
    protected function loadSpeaker($slug)
    {
        return Speaker::isPublished()->with('photo_file')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Load sessions for a speaker
     */
    protected function loadSpeakerSessions($speakerSlug)
    {
        $speaker = Speaker::isPublished()->with('photo_file')
            ->where('slug', $speakerSlug)
            ->first();

        if (!$speaker) {
            return collect();
        }

        return Session::with(['day', 'venue'])
            ->isPublished()
            ->ordered()
            ->whereHas('speakers', function($query) use ($speaker) {
                $query->where('speaker_id', $speaker->id);
            })
            ->get();
    }

    /**
     * Load speakers list
     */
    protected function loadSpeakers()
    {
        $query = Speaker::isPublished()->with('photo_file');

        if ($this->property('showFeaturedOnly')) {
            $query->isFeatured();
        }

        $order = $this->property('order', 'full_name');
        $query->orderBy($order, 'asc');

        if ($limit = $this->property('limit')) {
            $query->take($limit);
        }

        return $query->get();
    }

    /**
     * Get speaker photo URL with fallback
     */
    public function getPhotoUrl($speaker, $default = null)
    {
        return $this->getSpeakerImageUrl($speaker) ?: ($default ?: '/plugins/majos/conference/assets/images/default-speaker.svg');
    }

    public function getSpeakerPopupData($speaker)
    {
        return SpeakerPopupData::encode($speaker);
    }

    public function getSpeakerImageUrl($speaker)
    {
        return SpeakerPopupData::getImageUrl($speaker);
    }

    public function getSpeakerExpertiseArray($speaker)
    {
        return SpeakerPopupData::getExpertise($speaker);
    }

    /**
     * Format expertise as array
     */
    public function getExpertiseArray($speaker)
    {
        return $this->getSpeakerExpertiseArray($speaker);
    }
}
