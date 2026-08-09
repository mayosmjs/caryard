<?php namespace Majos\Conference\Models;

use Model;

/**
 * Session Model
 */
class Session extends Model
{
    /**
     * @var string table name for the model.
     */
    protected $table = 'majos_conference_sessions';

    /**
     * @var array guarded attributes
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'conference_day_id',
        'venue_id',
        'title',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'session_type',
        'track',
        'capacity',
        'sort_order',
        'is_published'
    ];

    /**
     * @var array belongsTo relationships
     */
    public $belongsTo = [
        'day' => [ConferenceDay::class, 'key' => 'conference_day_id'],
        'venue' => [Venue::class, 'key' => 'venue_id'],
    ];

    /**
     * @var array belongsToMany relationships
     */
    public $belongsToMany = [
        'speakers' => [
            Speaker::class,
            'table' => 'majos_conference_session_speaker',
            'timestamps' => true,
        ],
    ];

    /**
     * @var array dates to convert to Carbon instances
     */
    protected $dates = [
        'starts_at',
        'ends_at',
        'created_at',
        'updated_at'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'title' => 'required',
        'slug' => 'required|unique:majos_conference_sessions,slug',
        'starts_at' => 'required',
        'ends_at' => 'required|after:starts_at'
    ];

    /**
     * Scope for published sessions
     */
    public function scopeIsPublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for upcoming sessions
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope for past sessions
     */
    public function scopePast($query)
    {
        return $query->where('ends_at', '<', now());
    }

    /**
     * Scope for current sessions (happening now)
     */
    public function scopeCurrent($query)
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * Scope for sessions on a specific day
     */
    public function scopeOnDay($query, $dayId)
    {
        return $query->where('conference_day_id', $dayId);
    }

    /**
     * Scope for sessions by track
     */
    public function scopeByTrack($query, $track)
    {
        return $query->where('track', $track);
    }

    /**
     * Scope for sessions by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('session_type', $type);
    }

    /**
     * Scope for ordered sessions
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderBy('starts_at', 'asc');
    }

    /**
     * Check if session is full
     */
    public function isFull()
    {
        if (!$this->capacity) {
            return false;
        }
        // This would need a registrations table to be accurate
        return false;
    }

    /**
     * Get duration in minutes
     */
    public function getDurationInMinutes()
    {
        if (!$this->starts_at || !$this->ends_at) {
            return 0;
        }
        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Get formatted time range
     */
    public function getTimeRange()
    {
        if (!$this->starts_at || !$this->ends_at) {
            return '';
        }
        return $this->starts_at->format('H:i') . ' - ' . $this->ends_at->format('H:i');
    }

    /**
     * Dropdown options for conference day field
     */
    public function getConferenceDayIdOptions()
    {
        return ConferenceDay::getDayOptions();
    }

    /**
     * Dropdown options for venue field
     */
    public function getVenueIdOptions()
    {
        return Venue::getVenueOptions();
    }

    /**
     * Sync speakers after save
     */
    public function afterSave()
    {
        parent::afterSave();

        // Sync speakers if the speakers field was posted
        if (post('speakers')) {
            $speakerIds = post('speakers', []);
            $this->speakers()->sync($speakerIds);
        }
    }
}
