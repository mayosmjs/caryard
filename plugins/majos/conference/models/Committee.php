<?php namespace Majos\Conference\Models;

use Model;
use System\Models\File;

/**
 * Committee Model
 */
class Committee extends Model
{
    /**
     * @var string table name for the model.
     */
    protected $table = 'majos_conference_committees';

    /**
     * @var array guarded attributes
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'name',
        'designation',
        'description',
        'committee_type',
        'sort_order',
        'is_published',
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
        'designation' => 'required',
        'committee_type' => 'required',
    ];

    public $attachOne = [
        'image' => [
            File::class,
            'public' => true,
            'thumbOptions' => ['mode' => 'crop', 'extension' => 'jpg', 'quality' => 80],
        ],
    ];

    /**
     * Scope for published committee members.
     */
    public function scopeIsPublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Get image URL with fallback.
     */
    public function getImageUrl($width = 360, $height = 420)
    {
        if ($this->image) {
            return url($this->image->getThumb($width, $height, ['mode' => 'crop']));
        }

        return url('/plugins/majos/conference/assets/images/default-speaker.svg');
    }
}
