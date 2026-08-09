<?php namespace Majos\Conference\Models;

use Model;
use System\Models\File;


/**
 * Speaker Model
 */
class Speaker extends Model
{
    /**
     * @var string table name for the model.
     */
    protected $table = 'majos_conference_speakers';

    /**
     * @var array guarded attributes
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes
     */
    protected $fillable = [
        'full_name',
        'slug',
        'title',
        'country',
        'expertise',
        'company',
        'email',
        'linkedin_url',
        'website_url',
        'biography',
        'is_featured',
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
        'full_name' => 'required',
        'slug' => 'required|unique:majos_conference_speakers,slug'
    ];
    

    public $attachOne = [
        'photo_file' => [
            File::class,'public' => true, 
            'thumbOptions' => ['mode' => 'crop', 'extension' => 'jpg', 'quality' => 80]
            ]
    ];

    /**
     * Relationship: sessions this speaker is presenting
     */
    public function sessions()
    {
        return $this->belongsToMany(Session::class, 'majos_conference_session_speaker')
            ->withTimestamps();
    }

    /**
     * Scope for published speakers
     */
    public function scopeIsPublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for featured speakers
     */
    public function scopeIsFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get photo URL with fallback
     */
    public function getPhotoUrl($default = null)
    {
        if ($this->photo_file) {
            return url($this->photo_file->getThumb(200, 200, ['mode' => 'crop']));
        }
        return url($default ?: '/plugins/majos/conference/assets/images/default-speaker.svg');
    }
}