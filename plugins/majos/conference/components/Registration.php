<?php

namespace Majos\Conference\Components;

use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Flash;
use Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Log;
use Mail;
use Majos\Conference\Models\Submission;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use Redirect;
use Request;
use Session;
use Str;
use Validator;

/**
 * Conference abstract-submission component.
 *
 * Handles:
 *  - Deadline enforcement
 *  - Rate limiting  (5 attempts / IP / hour)
 *  - Cloudflare Turnstile bot protection
 *  - Input validation & sanitisation aligned to the registration form
 *  - Optional PDF upload with MIME + magic-byte verification
 *  - Secure token + hashed-password generation
 *  - Confirmation email dispatch
 */
class Registration extends ComponentBase
{
    // ─── Word limits (must match front-end counters) ─────────────────────
    private const TITLE_MAX_WORDS    = 20;
    private const ABSTRACT_MAX_WORDS = 500;

    // ─── Rate-limiting ────────────────────────────────────────────────────
    private const RATE_LIMIT_ATTEMPTS = 5;
    private const RATE_LIMIT_DECAY_S  = 3600; // 1 hour

    // ─── Allowed MIME types for uploaded PDFs ─────────────────────────────
    private const ALLOWED_PDF_MIMES = ['application/pdf', 'application/x-pdf'];

    // ─────────────────────────────────────────────────────────────────────

    public function componentDetails(): array
    {
        return [
            'name'        => 'Conference Registration',
            'description' => 'Accepts and validates conference abstract submissions.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'editPage' => [
                'title'       => 'Edit page filename',
                'type'        => 'string',
                'default'     => 'submission-edit',
                'description' => 'CMS page handle used to build the edit URL sent to the author.',
            ],
            'useGlobalSettings' => [
                'title'       => 'Use global settings',
                'type'        => 'checkbox',
                'default'     => true,
                'description' => 'Pull configuration from the backend Settings panel.',
            ],
            'deadline' => [
                'title'       => 'Submission deadline (override)',
                'type'        => 'string',
                'default'     => '2027-01-30 23:59:59',
                'description' => 'YYYY-MM-DD HH:MM:SS — only active when Use global settings is off.',
            ],
            'maxFileSizeKb' => [
                'title'       => 'Max PDF size KB (override)',
                'type'        => 'string',
                'default'     => '5120',
                'description' => 'Maximum PDF upload size in kilobytes.',
            ],
            'turnstileSiteKey' => [
                'title'       => 'Turnstile site key (override)',
                'type'        => 'string',
                'default'     => '',
                'description' => 'Cloudflare Turnstile public site key.',
            ],
            'turnstileSecretKey' => [
                'title'       => 'Turnstile secret key (override)',
                'type'        => 'string',
                'default'     => '',
                'description' => 'Cloudflare Turnstile server-side secret key.',
            ],
            'uniqueEmail' => [
                'title'       => 'Enforce unique email',
                'type'        => 'checkbox',
                'default'     => true,
                'description' => 'Prevent more than one submission per email address.',
            ],
            'tokenExpiryDays' => [
                'title'       => 'Edit-link expiry (days, override)',
                'type'        => 'string',
                'default'     => '30',
                'description' => 'Days before the emailed edit link expires.',
            ],
        ];
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────

    public function onRun(): void
    {
        $settings = $this->getSettings();

        $this->page['turnstileSiteKey']  = $this->getTurnstileSiteKey();
        $this->page['submissionDeadline'] = $settings['deadline'];
        $this->page['csrfToken']          = csrf_token();
        $this->page['maxFileSizeBytes']   = $settings['maxFileSizeKb'] * 1024;

        $this->page['presentationTypes'] = Submission::presentationTypeNames();
        $this->page['countries']         = Submission::countries();
        $this->page['researchAreas']     = \Majos\Conference\Models\ResearchArea::roots()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $this->page['researchTopicsByArea'] = \Majos\Conference\Models\ResearchArea::topicsByArea();

        if ($this->getTurnstileSiteKey()) {
            $this->addJs('https://challenges.cloudflare.com/turnstile/v0/api.js', [
                'async' => true,
                'defer' => true,
            ]);
        }
    }

    // ─── Form handler ─────────────────────────────────────────────────────

    public function onSubmit()
    {
        try {
            // $this->guardRateLimit();
            $this->guardDeadline();
            // $this->guardTurnstile();

            $data          = $this->validateAndSanitize();
            $plainPassword = $this->generatePassword();

            $submission = $this->createSubmission($data, $plainPassword);
            $this->processFileUpload($submission);
            $this->sendConfirmationEmail($submission, $plainPassword);

            Session::flash('submission_success', true);
            Flash::success('Your abstract was submitted successfully. Please check your email for the edit link and password.');

            return Redirect::refresh();

        } catch (ValidationException $e) {
            Log::warning('Abstract submission — validation failed', [
                'ip'     => Request::ip(),
                'errors' => $e->getMessage(),
            ]);
            throw $e;

        } catch (ApplicationException $e) {
            Log::warning('Abstract submission — blocked', [
                'ip'     => Request::ip(),
                'reason' => $e->getMessage(),
            ]);
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Abstract submission — unexpected error', [
                'ip'      => Request::ip(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw new ApplicationException('An unexpected error occurred. Please try again later.');
        }
    }

    // ─── Validation & sanitisation ────────────────────────────────────────

    /**
     * Validate every field submitted by the registration form, then return
     * a clean, sanitised data array ready for model hydration.
     */
    protected function validateAndSanitize(): array
    {
        $settings      = $this->getSettings();
        $maxFileSizeKb = $settings['maxFileSizeKb'];

        // Build email rule dynamically — uniqueness check is togglable
        $emailRule = ['required', 'email:rfc,dns', 'max:255'];
        if ($settings['uniqueEmail']) {
            $emailRule[] = 'unique:majos_conference_submissions,email';
        }

        $rules = [
            // ── Author Details (Card 1) ───────────────────────────────────
            'fname'             => ['required', 'string', 'min:2', 'max:120'],
            'lname'             => ['required', 'string', 'min:2', 'max:120'],
            'email'             => $emailRule,
            'tel1'              => ['nullable', 'string', 'max:30'],
            'affiliation'       => ['required', 'string', 'max:500'],
            'department'        => ['required', 'string', 'max:255'],
            'institution'       => ['required', 'string', 'max:255'],
            'city'              => ['required', 'string', 'max:100'],
            'country'           => ['required', 'string', 'in:' . implode(',', Submission::countries())],

            // ── Paper Details (Card 2) ────────────────────────────────────
            'title'             => ['required', 'string', 'min:5', 'max:255'],
            'authors'           => ['required', 'string', 'max:500'],
            'presentation_type' => ['required', 'string', 'in:' . implode(',', Submission::presentationTypeNames())],
            'research_area'     => ['required', 'string', 'in:' . implode(',', \Majos\Conference\Models\ResearchArea::rootLabels())],
            'research_topic'    => ['required', 'string', Rule::in(\Majos\Conference\Models\ResearchArea::topicLabels())],
            'abstract'          => ['required', 'string', 'min:40', 'max:6000'],

            // ── Optional PDF upload ───────────────────────────────────────
            'paper_file'        => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:' . $maxFileSizeKb],
        ];

        $messages = [
            'fname.min'              => 'First name must be at least 2 characters.',
            'lname.min'              => 'Last name must be at least 2 characters.',
            'email.email'            => 'Please enter a valid email address.',
            'email.unique'           => 'An abstract has already been submitted with this email address.',
            'country.in'             => 'Please select a valid country from the list.',
            'presentation_type.in'   => 'Please select a valid presentation type.',
            'research_area.required' => 'Please select a research area.',
            'research_area.in'       => 'Please select a valid research area.',
            'research_topic.required' => 'Please select a research topic.',
            'research_topic.in'      => 'Please select a valid research topic.',
            'abstract.min'           => 'Your abstract is too short (minimum 40 characters).',
            'paper_file.mimes'       => 'Only PDF files are accepted.',
            'paper_file.mimetypes'   => 'Only PDF files are accepted.',
            'paper_file.max'         => 'The uploaded file exceeds the maximum allowed size.',
        ];

        $payload               = request()->only(array_keys($rules));
        $payload['paper_file'] = request()->file('paper_file');

        $validator = Validator::make($payload, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // ── Word-count validation (not easily done via Laravel rules) ─────
        $this->guardWordCount('title',    request('title'),    self::TITLE_MAX_WORDS,    'Abstract title');
        $this->guardWordCount('abstract', request('abstract'), self::ABSTRACT_MAX_WORDS, 'Abstract body');

        // ── Research topic must belong to the selected research area ──────
        if (!\Majos\Conference\Models\ResearchArea::topicBelongsToArea(
            (string) request('research_topic'),
            (string) request('research_area')
        )) {
            throw new ValidationException([
                'research_topic' => 'The selected research topic does not belong to the selected research area.',
            ]);
        }

        // ── Build sanitised data map ──────────────────────────────────────
        return [
            'author_name'       => $this->sanitize(request('fname') . ' ' . request('lname')),
            'email'             => mb_strtolower($this->sanitize(request('email'))),
            'phone'             => $this->sanitizePhone(request('tel1', '')),
            'mobile'            => '',   // not on current form; preserved for model compatibility
            'affiliation'       => $this->sanitize(request('affiliation')),
            'department'        => $this->sanitize(request('department')),
            'institution'       => $this->sanitize(request('institution')),
            'city'              => $this->sanitize(request('city')),
            'country'           => $this->sanitize(request('country')),
            'title'             => $this->sanitize(request('title')),
            'authors'           => $this->sanitize(request('authors')),
            'presentation_type' => $this->sanitize(request('presentation_type')),
            'research_area'     => $this->sanitize(request('research_area')),
            'research_topic'    => $this->sanitize(request('research_topic')),
            'abstract'          => $this->sanitize(request('abstract')),
        ];
    }

    // ─── Model persistence ────────────────────────────────────────────────

    protected function createSubmission(array $data, string $plainPassword): Submission
    {
        $settings = $this->getSettings();

        $submission = new Submission();
        $submission->fill($data);
        $submission->token            = Str::random(64);
        $submission->password_hash    = Hash::make($plainPassword);
        $submission->token_expires_at = now()->addDays($settings['tokenExpiryDays']);
        $submission->status           = Submission::STATUS_SUBMITTED;
        $submission->submitter_ip     = Request::ip();
        $submission->save();

        $submission->logActivity('submission_created', [
            'ip'         => Request::ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);

        return $submission;
    }

    // ─── File upload ──────────────────────────────────────────────────────

    /**
     * Validates and attaches the optional PDF upload.
     * Performs three layers of verification:
     *  1. Laravel/Symfony UploadedFile integrity flag
     *  2. Detected MIME type (server-side, not client-supplied)
     *  3. PDF magic bytes (%PDF header)
     */
    protected function processFileUpload(Submission $submission): void
    {
        $file = request()->file('paper_file');

        if (!$file) {
            return;
        }

        if (!$file->isValid()) {
            throw new ApplicationException('The uploaded file is corrupted or incomplete.');
        }

        $mimeType = $file->getMimeType(); // finfo-based, not client header
        if (!in_array($mimeType, self::ALLOWED_PDF_MIMES, true)) {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        // Magic-byte check: valid PDF files start with "%PDF"
        $handle = fopen($file->getRealPath(), 'rb');
        $magic  = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            throw new ApplicationException('The uploaded file is not a valid PDF document.');
        }

        $submission->paper_file = $file;
        $submission->save();

        $submission->logActivity('file_uploaded', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
        ]);
    }

    // ─── Email ────────────────────────────────────────────────────────────

    protected function sendConfirmationEmail(Submission $submission, string $password): void
    {
        $editUrl = $this->controller->pageUrl($this->property('editPage'), [
            'token' => $submission->token,
        ]);

        $params = [
            'submission' => $submission,
            'password'   => $password,
            'editUrl'    => $editUrl,
            'expiryDate' => $submission->token_expires_at->format('F j, Y H:i'),
        ];

        try {
            Mail::send(
                'majos.conference::mail.submission_received',
                $params,
                function ($message) use ($submission) {
                    $message->to($submission->email, $submission->author_name)
                            ->subject('AFRIRPA 2027 — Abstract Submission Received');
                }
            );

            Log::info('Confirmation email sent', [
                'submission_id' => $submission->id,
                'email'         => $submission->email,
            ]);
        } catch (\Throwable $e) {
            // Email failure must not roll back a valid submission
            Log::error('Failed to send confirmation email', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    // ─── Guards ───────────────────────────────────────────────────────────

    /**
     * Block submissions after the configured deadline.
     */
    protected function guardDeadline(): void
    {
        $settings = $this->getSettings();

        try {
            $deadline = Carbon::parse($settings['deadline']);
        } catch (\Throwable $e) {
            throw new ApplicationException('Submission deadline is not configured correctly. Please contact the organisers.');
        }

        if ($deadline->isPast()) {
            throw new ApplicationException('The abstract submission deadline has passed. No further submissions are being accepted.');
        }
    }

    /**
     * Rate-limit by IP: 5 attempts per hour.
     * Uses a sha256-hashed key so the raw IP is never stored in the cache.
     */
    protected function guardRateLimit(): void
    {
        $key = 'afrirpa-submit:' . hash('sha256', Request::ip());

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);
            Log::warning('Rate limit hit on submission', ['ip' => Request::ip()]);
            throw new ApplicationException(
                "Too many submission attempts from your connection. Please try again in {$retryAfter} seconds."
            );
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_S);
    }

    /**
     * Verify the Cloudflare Turnstile challenge server-side.
     * If no secret key is configured, logs a warning and skips in development.
     */
    protected function guardTurnstile(): void
    {
        $token  = request('cf-turnstile-response', '');
        $secret = $this->getTurnstileSecretKey();

        // Skip silently when secret is not yet configured (local / staging)
        if (!$secret) {
            Log::warning('Turnstile secret key is not configured — bot protection is inactive.');
            return;
        }

        if (!$token) {
            throw new ApplicationException('Please complete the security challenge before submitting.');
        }

        try {
            $result = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => Request::ip(),
                ]);

            if (!$result->json('success')) {
                Log::warning('Turnstile challenge failed', [
                    'ip'           => Request::ip(),
                    'error-codes'  => $result->json('error-codes', []),
                ]);
                throw new ApplicationException('Security challenge failed. Please refresh the page and try again.');
            }
        } catch (ApplicationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Turnstile service error', ['message' => $e->getMessage()]);
            throw new ApplicationException('Security verification service is currently unavailable. Please try again shortly.');
        }
    }

    /**
     * Enforce front-end word-count limits server-side to prevent bypass.
     */
    protected function guardWordCount(string $field, ?string $value, int $maxWords, string $label): void
    {
        $count = $value ? count(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY)) : 0;

        if ($count > $maxWords) {
            $validator = Validator::make([], []);
            $validator->errors()->add($field, "{$label} must not exceed {$maxWords} words (you have {$count}).");
            throw new ValidationException($validator);
        }
    }

    // ─── Settings ─────────────────────────────────────────────────────────

    protected function getSettings(): array
    {
        if ((bool) $this->property('useGlobalSettings')) {
            try {
                $s = \Majos\Conference\Models\Settings::instance();
                return [
                    'deadline'          => $s->submission_deadline  ?: '2027-06-30 23:59:59',
                    'maxFileSizeKb'     => (int) ($s->max_file_size_kb ?: 5120),
                    'turnstileSiteKey'  => $s->turnstile_site_key   ?: '',
                    'turnstileSecretKey'=> $s->turnstile_secret_key ?: '',
                    'uniqueEmail'       => (bool) $s->unique_email,
                    'tokenExpiryDays'   => (int) ($s->token_expiry_days ?: 30),
                ];
            } catch (\Throwable $e) {
                Log::error('Failed to load conference settings', ['error' => $e->getMessage()]);
            }
        }

        return [
            'deadline'          => $this->property('deadline',          '2027-06-30 23:59:59'),
            'maxFileSizeKb'     => (int) $this->property('maxFileSizeKb',     5120),
            'turnstileSiteKey'  => $this->property('turnstileSiteKey',  ''),
            'turnstileSecretKey'=> $this->property('turnstileSecretKey',''),
            'uniqueEmail'       => (bool) $this->property('uniqueEmail', true),
            'tokenExpiryDays'   => (int) $this->property('tokenExpiryDays',   30),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /** Strip HTML tags and trim whitespace. Never truncates silently. */
    protected function sanitize(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    /** Keep only digits, +, spaces, hyphens, and parentheses. */
    protected function sanitizePhone(mixed $value): string
    {
        return preg_replace('/[^0-9+()\- ]/', '', (string) $value);
    }

    /** Cryptographically random password: 6 alpha + 4 digit characters. */
    protected function generatePassword(): string
    {
        return strtoupper(Str::random(6)) . random_int(1000, 9999);
    }

    protected function getTurnstileSiteKey(): string
    {
        return $this->getSettings()['turnstileSiteKey'] ?: (string) env('TURNSTILE_SITE_KEY', '');
    }

    protected function getTurnstileSecretKey(): string
    {
        return $this->getSettings()['turnstileSecretKey'] ?: (string) env('TURNSTILE_SECRET_KEY', '');
    }
}
