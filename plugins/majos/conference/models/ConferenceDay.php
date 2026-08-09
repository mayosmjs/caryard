<?php namespace Majos\Conference\Models;

use Model;

/**
 * Conference Day Model
 */
class ConferenceDay extends Model
{
    /**
     * @var string table name for the model.
     */
    protected $table = 'majos_conference_days';

    /**
     * @var array guarded attributes
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'title',
        'date',
        'slug',
        'sort_order',
        'is_published'
    ];

    /**
     * @var array dates to convert to Carbon instances
     */
    protected $dates = ['date', 'created_at', 'updated_at'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'title' => 'required',
        'date' => 'required',
        'slug' => 'required|unique:majos_conference_days,slug'
    ];

    /**
     * Relationship: sessions for this day
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'conference_day_id');
    }

    /**
     * Scope for published days
     */
    public function scopeIsPublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for ordered days
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('date', 'asc');
    }

    /**
     * Get dropdown options for forms
     */
    public static function getDayOptions()
    {
        return self::isPublished()
            ->ordered()
            ->get()
            ->pluck('title', 'id')
            ->toArray();
    }
}