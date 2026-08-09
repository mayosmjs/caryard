<?php namespace Majos\Conference\Models;

use Model;
use System\Models\File;
use October\Rain\Database\Traits\Validation;
use Log;

class Submission extends Model
{
    use Validation;

    public $table = 'majos_conference_submissions';

    // ── Status constants ──────────────────────────────────────────────────
    public const STATUS_SUBMITTED           = 'submitted';
    public const STATUS_ASSIGNED_FOR_REVIEW = 'assigned_for_review';
    public const STATUS_REVIEWED            = 'reviewed';
    public const STATUS_ACCEPTED            = 'accepted';
    public const STATUS_REJECTED            = 'rejected';

    // ── Reviewer decision values ──────────────────────────────────────────
    public const REVIEWER_APPROVED = 'approved';
    public const REVIEWER_REJECTED = 'rejected';

    // ── Presentation types that require a poster template ────────────────
    public const POSTER_TYPES = [
        'Poster-Oral Presentation',
        'Poster Presentation',
    ];

    public const MAX_POSTER_SIZE_MB = 25;

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

    /**
     * Full alphabetical list of countries for the submission form's country
     * selector. Loaded once from the plugin data file so the front-end
     * dropdown and the server-side `in:` validation always agree.
     *
     * @return string[]
     */
    public static function countries(): array
    {
        static $countries = null;

        if ($countries === null) {
            $path = base_path('plugins/majos/conference/data/countries.php');
            $list = is_file($path) ? require $path : [];

            $countries = is_array($list) ? array_values($list) : [];
        }

        return $countries;
    }

    protected $rules = [
        'author_name' => 'required|string|min:2|max:255',
        'email' => 'required|email|max:255',
        'title' => 'required|string|min:5|max:255',
        'category' => 'nullable|string|max:255',
        'research_area' => 'nullable|string|max:255',
        'research_topic' => 'nullable|string|max:255',
        'abstract' => 'required|string|min:40|max:6000',
        'affiliation' => 'required|string|max:2000',
        'department' => 'required|string|max:255',
        'institution' => 'required|string|max:255',
        'city' => 'required|string|max:255',
        'country' => 'required|string',
        'presentation_type' => 'required|string',
        'approved_presentation_type' => 'nullable|string',
        // 'keywords' => 'required|array|min:3|max:5',
        // 'keywords.*' => 'string|max:80',
    ];

    protected $jsonable = ['keywords'];

    protected $dates = [
        'token_expires_at',
        'decided_at',
        'assigned_at',
        'reviewed_at',
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
        'approved_presentation_type',
        'category',
        'research_area',
        'research_topic',
        'abstract',
        'keywords',
        'submitter_ip',
        'reviewer_id',
        'assigned_at',
        'reviewer_decision',
        'reviewer_feedback',
        'reviewed_at',
        'admin_notes',
    ];


    

    public $attachOne = [
        'paper_file' => [
            'Majos\Conference\Models\ProtectedFile'
        ],
    ];

    public $belongsTo = [
        'reviewer' => ['Majos\Conference\Models\Reviewer', 'key' => 'reviewer_id'],
        'decider'  => ['Backend\Models\User',              'key' => 'decided_by'],
    ];

    public $timestamps = true;

    protected $appends = ['keywords_list'];

    public function beforeValidate()
    {
        $this->normalizeEmail();
        $this->normalizeKeywords();

        $types = implode(',', self::presentationTypeNames());
        $this->rules['presentation_type'] = 'required|string|in:' . $types;
        $this->rules['approved_presentation_type'] = 'nullable|string|in:' . $types;

        $countries = implode(',', self::countries());
        $this->rules['country'] = 'required|string|in:' . $countries;
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

    // ── Workflow state checks ─────────────────────────────────────────────

    public function canBeAssigned(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_ASSIGNED_FOR_REVIEW, // allow re-assignment
        ]);
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_ASSIGNED_FOR_REVIEW;
    }

    public function canReceiveFinalDecision(): bool
    {
        return $this->status === self::STATUS_REVIEWED;
    }

    /**
     * @deprecated Use canReceiveFinalDecision() for the new review workflow.
     */
    public function canBeDecided(): bool
    {
        return $this->canReceiveFinalDecision();
    }

    public function requiresPosterTemplate(): bool
    {
        return \Majos\Conference\Models\Settings::instance()
            ->requiresPosterTemplate((string) $this->approved_presentation_type);
    }

    public function getApprovedPresentationTypeOptions()
    {
        return array_combine(self::presentationTypeNames(), self::presentationTypeNames());
    }

    /**
     * presentationTypeNames returns the presentation type names from the
     * presentation types table, falling back to the hardcoded defaults.
     */
    public static function presentationTypeNames(): array
    {
        try {
            $names = \Majos\Conference\Models\PresentationType::orderBy('id')->pluck('name')->all();
        } catch (\Throwable $e) {
            $names = [];
        }

        return array_values(array_filter(array_map('trim', $names))) ?: self::PRESENTATION_TYPES;
    }

    /**
     * getPresentationTypeOptions is used by form fields, list filters and
     * the settings widget to build the presentation type option list.
     */
    public function getPresentationTypeOptions(...$args)
    {
        return array_combine(self::presentationTypeNames(), self::presentationTypeNames());
    }

    public function getResearchAreaOptions()
    {
        return array_combine(
            \Majos\Conference\Models\ResearchArea::rootLabels(),
            \Majos\Conference\Models\ResearchArea::rootLabels()
        );
    }

    public function getResearchTopicOptions()
    {
        return array_combine(
            \Majos\Conference\Models\ResearchArea::topicLabels(),
            \Majos\Conference\Models\ResearchArea::topicLabels()
        );
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeAssignedForReview($query)
    {
        return $query->where('status', self::STATUS_ASSIGNED_FOR_REVIEW);
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', self::STATUS_REVIEWED);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeAssignedToReviewer($query, int $reviewerId)
    {
        return $query->where('reviewer_id', $reviewerId)
                     ->whereIn('status', [self::STATUS_ASSIGNED_FOR_REVIEW, self::STATUS_REVIEWED]);
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
            self::STATUS_SUBMITTED           => 'Submitted',
            self::STATUS_ASSIGNED_FOR_REVIEW => 'Pending Review',
            self::STATUS_REVIEWED            => 'Reviewed — Awaiting Final Decision',
            self::STATUS_ACCEPTED            => 'Accepted',
            self::STATUS_REJECTED            => 'Rejected',
        ];
    }

    public function getReviewerDecisionOptions()
    {
        return [
            self::REVIEWER_APPROVED => 'Approved',
            self::REVIEWER_REJECTED => 'Rejected',
        ];
    }
}
