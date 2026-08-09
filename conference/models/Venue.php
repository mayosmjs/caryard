<?php namespace Majos\Conference\Models;

use Model;

/**
 * Venue Model
 */
class Venue extends Model
{
    /**
     * @var string table name for the model.
     */
    protected $table = 'majos_conference_venues';

    /**
     * @var array guarded attributes
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'name',
        'location',
        'description',
        'capacity',
        'slug',
        'sort_order',
        'is_published'
    ];

    /**
     * @var array dates to convert to Carbon instances
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'name' => 'required',
        'slug' => 'required|unique:majos_conference_venues,slug'
    ];

    /**
     * Relationship: sessions in this venue
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'venue_id');
    }

    /**
     * Scope for published venues
     */
    public function scopeIsPublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for ordered venues
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get dropdown options for forms
     */
    public static function getVenueOptions()
    {
        return self::isPublished()
            ->ordered()
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }
}