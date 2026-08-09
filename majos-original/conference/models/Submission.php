<?php namespace Majos\Conference\Models;

use Model;
use System\Models\File;
use October\Rain\Database\Traits\Validation;
use Log;

class Submission extends Model
{
    use Validation;

    public $table = 'majos_conference_submissions';

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const PRESENTATION_TYPES = [
        'Oral Presentation',
        'Poster-Oral Presentation',
        'Poster Presentation',
        'Young Scientist Competition',
    ];

    public const CATEGORIES = [
        'Artificial Intelligence',
        'Brachytherapy',
        'Nuclear Medicine',
        'Particle Therapy',
        'Quality Assurance',
        'Radiation Biology',
        'Radiation Imaging',
        'Radiation Protection',
        'Radiation Therapy',
        'Others',
    ];

    public const VALID_COUNTRIES = [
        'Kenya', 'South Africa', 'Ghana', 'Egypt', 'Rwanda',
        'Tanzania', 'United States of America', 'United Kingdom', 'Other',
    ];

    protected $rules = [
        'author_name' => 'required|string|min:2|max:255',
        'email' => 'required|email|max:255',
        'title' => 'required|string|min:5|max:255',
        'category' => 'required|string|in:Artificial Intelligence,Brachytherapy,Nuclear Medicine,Particle Therapy,Quality Assurance,Radiation Biology,Radiation Imaging,Radiation Protection,Radiation Therapy,Others',
        'abstract' => 'required|string|min:40|max:6000',
        'affiliation' => 'required|string|max:2000',
        'department' => 'required|string|max:255',
        'institution' => 'required|string|max:255',
        'city' => 'required|string|max:255',
        'country' => 'required|string|in:Kenya,South Africa,Ghana,Egypt,Rwanda,Tanzania,United States of America,United Kingdom,Other',
        'presentation_type' => 'required|string|in:Oral Presentation,Poster-Oral Presentation,Poster Presentation,Young Scientist Competition',
        'keywords' => 'required|array|min:3|max:5',
        'keywords.*' => 'string|max:80',
    ];

    protected $jsonable = ['keywords'];

    protected $dates = [
        'token_expires_at',
        'decided_at',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'token',
        'password_hash',
        'token_expires_at',
        'status',
        'author_name',
        'email',
        'affiliation',
        'department',
        'institution',
        'city',
        'country',
        'phone',
        'mobile',
        'title',
        'authors',
        'presentation_type',
        'category',
        'abstract',
        'keywords',
        'submitter_ip',
    ];

    public $attachOne = [
        'paper_file' => File::class,
    ];

    public $timestamps = true;

    protected $appends = ['keywords_list'];

    public function beforeValidate()
    {
        $this->normalizeEmail();
        $this->normalizeKeywords();
    }

    public function beforeSave()
    {
        $this->normalizePhoneNumbers();
    }

    public function getKeywordsListAttribute(): string
    {
        return collect((array) $this->keywords)->filter()->implode(', ');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_SUBMITTED
            && $this->token_expires_at
            && $this->token_expires_at->isFuture();
    }

    public function canBeDecided(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    protected function normalizeEmail(): void
    {
        if ($this->email) {
            $this->email = strtolower(trim($this->email));
        }
    }

    protected function normalizeKeywords(): void
    {
        if (is_array($this->keywords)) {
            $this->keywords = array_values(array_filter(array_map(function ($kw) {
                return trim(strip_tags((string) $kw));
            }, $this->keywords)));
        }
    }

    protected function normalizePhoneNumbers(): void
    {
        if ($this->phone) {
            $this->phone = preg_replace('/[^0-9+]/', '', $this->phone);
        }
        if ($this->mobile) {
            $this->mobile = preg_replace('/[^0-9+]/', '', $this->mobile);
        }
    }

    public function logActivity(string $action, array $context = []): void
    {
        Log::info('Conference Submission Activity', array_merge([
            'submission_id' => $this->id,
            'action' => $action,
            'status' => $this->status,
            'email' => $this->email,
        ], $context));
    }

    public function getStatusOptions()
    {
        return [
            'submitted' => 'Submitted',
            'accepted'  => 'Accepted',
            'rejected'  => 'Rejected',
        ];
    }
}
