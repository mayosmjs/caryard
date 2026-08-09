<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Flash;
use Hash;
use Log;
use Redirect;
use Request;
use Session;
use Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use Majos\Conference\Models\Submission;

class EditSubmission extends ComponentBase
{
    public $submission;

    // ─── Constants ────────────────────────────────────────────────────────
    private const TITLE_MAX_WORDS    = 20;
    private const ABSTRACT_MAX_WORDS = 500;
    private const RATE_LIMIT_ATTEMPTS = 8;
    private const RATE_LIMIT_DECAY_S  = 3600;
    private const ALLOWED_PDF_MIMES  = ['application/pdf', 'application/x-pdf'];

    public function componentDetails()
    {
        return [
            'name' => 'Conference Edit Submission',
            'description' => 'Password-protected abstract edit form.',
        ];
    }

    public function defineProperties()
    {
        return [
            'useGlobalSettings' => [
                'title' => 'Use global settings',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Use settings from the backend configuration panel',
            ],
            'maxFileSizeKb' => [
                'title' => 'Max file size KB (override)',
                'type' => 'string',
                'default' => '5120',
                'description' => 'Maximum allowed PDF size (only used if Use global settings is unchecked)',
            ],
        ];
    }

    public function onRun()
    {
        $this->submission = $this->loadSubmission();
        $this->page['submission'] = $this->submission;
        $this->page['canEditSubmission'] = $this->canEditSubmission();
        $this->page['csrfToken'] = csrf_token();

        $this->page['presentationTypes'] = Submission::presentationTypeNames();
        $this->page['countries']         = Submission::countries();
        $this->page['researchAreas']     = \Majos\Conference\Models\ResearchArea::roots()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $this->page['researchTopicsByArea'] = \Majos\Conference\Models\ResearchArea::topicsByArea();

        $settings = $this->getSettings();
        $this->page['maxFileSizeBytes'] = $settings['maxFileSizeKb'] * 1024;
    }

    protected function getSettings(): array
    {
        if ((bool) $this->property('useGlobalSettings')) {
            try {
                $s = \Majos\Conference\Models\Settings::instance();
                return [
                    'maxFileSizeKb' => (int) ($s->max_file_size_kb ?: 5120),
                ];
            } catch (\Throwable $e) {
                Log::error('Failed to load conference settings', ['error' => $e->getMessage()]);
            }
        }

        return [
            'maxFileSizeKb' => (int) $this->property('maxFileSizeKb', 5120),
        ];
    }

    public function onAuthenticateEdit()
    {
        try {
            $this->guardRateLimit();
            $submission = $this->loadSubmissionOrFail();

            if (!$submission->isEditable()) {
                throw new ApplicationException('This edit link has expired or the submission is no longer editable.');
            }

            $password = (string) request('password', '');

            if (!$password || !Hash::check($password, $submission->password_hash)) {
                Log::warning('Failed password attempt on submission edit', [
                    'submission_id' => $submission->id,
                    'ip'            => Request::ip(),
                ]);
                throw new ApplicationException('The password is incorrect.');
            }

            Session::put($this->sessionKey(), $submission->id);
            $submission->logActivity('password_verified', ['ip' => Request::ip()]);

            Flash::success('Submission unlocked. You can now make changes.');

            return Redirect::refresh();

        } catch (ApplicationException $e) {
            throw $e;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Edit authentication error', [
                'message' => $e->getMessage(),
                'ip'      => Request::ip(),
                'token'   => $this->param('token'),
            ]);
            throw new ApplicationException('Authentication failed. Please try again.');
        }
    }

    public function onUpdateSubmission()
    {
        try {
            $submission = $this->loadSubmissionOrFail();

            if (!$this->isAuthorized($submission)) {
                throw new ApplicationException('Please unlock this submission before editing it.');
            }

            if (!$submission->isEditable()) {
                throw new ApplicationException('This edit link has expired or the submission is no longer editable.');
            }

            $data = $this->validateUpdateData();
            $this->updateSubmissionData($submission, $data);
            $this->processFileUpload($submission);

            $submission->logActivity('submission_updated', [
                'ip'         => Request::ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);

            Flash::success('Your submission has been updated successfully.');

            return Redirect::refresh();

        } catch (ValidationException $e) {
            Log::warning('Abstract update — validation failed', [
                'ip'     => Request::ip(),
                'errors' => $e->getMessage(),
            ]);
            throw $e;
        } catch (ApplicationException $e) {
            Log::warning('Abstract update — blocked', [
                'ip'     => Request::ip(),
                'reason' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Abstract update — unexpected error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'ip'      => Request::ip(),
            ]);
            throw new ApplicationException('Failed to update submission. Please try again.');
        }
    }

    protected function loadSubmission()
    {
        $token = $this->param('token');

        if (!$token) {
            return null;
        }

        return Submission::where('token', $token)->first();
    }

    protected function loadSubmissionOrFail(): Submission
    {
        $submission = $this->loadSubmission();

        if (!$submission) {
            throw new ApplicationException('Submission not found. The edit link may be invalid.');
        }

        return $submission;
    }

    protected function canEditSubmission(): bool
    {
        if (!$this->submission) {
            return false;
        }

        return Session::get($this->sessionKey()) === $this->submission->id
            && $this->submission->isEditable();
    }

    protected function isAuthorized(Submission $submission): bool
    {
        return Session::get($this->sessionKey()) === $submission->id;
    }

    protected function sessionKey(): string
    {
        return 'conference_submission_edit:' . $this->param('token');
    }

    protected function guardRateLimit(): void
    {
        $key = 'afrirpa-edit:' . hash('sha256', Request::ip());

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);
            Log::warning('Rate limit hit on submission edit', ['ip' => Request::ip()]);
            throw new ApplicationException(
                "Too many attempts from your connection. Please try again in {$retryAfter} seconds."
            );
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_S);
    }

    protected function validateUpdateData(): array
    {
        $settings      = $this->getSettings();
        $maxFileSizeKb = $settings['maxFileSizeKb'];

        $rules = [
            // ── Author Details ────────────────────────────────────────────
            'author_name'       => ['required', 'string', 'min:2', 'max:255'],
            'email'             => ['required', 'email:rfc,dns', 'max:255'],
            'tel1'              => ['nullable', 'string', 'max:30'],
            'affiliation'       => ['required', 'string', 'max:500'],
            'department'        => ['required', 'string', 'max:255'],
            'institution'       => ['required', 'string', 'max:255'],
            'city'              => ['required', 'string', 'max:100'],
            'country'           => ['required', 'string', 'in:' . implode(',', Submission::countries())],

            // ── Paper Details ─────────────────────────────────────────────
            'title'             => ['required', 'string', 'min:5', 'max:255'],
            'authors'           => ['required', 'string', 'max:500'],
            'presentation_type' => ['required', 'string', 'in:' . implode(',', Submission::presentationTypeNames())],
            'research_area'     => ['required', 'string', 'in:' . implode(',', \Majos\Conference\Models\ResearchArea::rootLabels())],
            'research_topic'    => ['required', 'string', Rule::in(\Majos\Conference\Models\ResearchArea::topicLabels())],
            'abstract'          => ['required', 'string', 'min:40', 'max:6000'],

            // ── Optional PDF ──────────────────────────────────────────────
            'paper_file'        => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:' . $maxFileSizeKb],
        ];

        $messages = [
            'author_name.min'       => 'Full name must be at least 2 characters.',
            'email.email'           => 'Please enter a valid email address.',
            'country.in'            => 'Please select a valid country from the list.',
            'presentation_type.in'  => 'Please select a valid presentation type.',
            'research_area.required' => 'Please select a research area.',
            'research_area.in'      => 'Please select a valid research area.',
            'research_topic.required' => 'Please select a research topic.',
            'research_topic.in'     => 'Please select a valid research topic.',
            'abstract.min'          => 'Your abstract is too short (minimum 40 characters).',
            'paper_file.mimes'      => 'Only PDF files are accepted.',
            'paper_file.mimetypes'  => 'Only PDF files are accepted.',
            'paper_file.max'        => 'The uploaded file exceeds the maximum allowed size.',
        ];

        $payload               = request()->only(array_keys($rules));
        $payload['paper_file'] = request()->file('paper_file');

        $validator = Validator::make($payload, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Word-count limits mirror front-end counters
        $this->guardWordCount('title',    request('title'),    self::TITLE_MAX_WORDS,    'Abstract title');
        $this->guardWordCount('abstract', request('abstract'), self::ABSTRACT_MAX_WORDS, 'Abstract body');

        // Research topic must belong to the selected research area
        if (!\Majos\Conference\Models\ResearchArea::topicBelongsToArea(
            (string) request('research_topic'),
            (string) request('research_area')
        )) {
            throw new ValidationException([
                'research_topic' => 'The selected research topic does not belong to the selected research area.',
            ]);
        }

        return [
            'author_name'       => $this->sanitize(request('author_name')),
            'email'             => mb_strtolower($this->sanitize(request('email'))),
            'phone'             => $this->sanitizePhone(request('tel1', '')),
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

    protected function updateSubmissionData(Submission $submission, array $data): void
    {
        $submission->fill($data);
        $submission->save();
    }

    protected function processFileUpload(Submission $submission): void
    {
        $file = request()->file('paper_file');

        if (!$file) {
            return;
        }

        if (!$file->isValid()) {
            throw new ApplicationException('The uploaded file is corrupted or incomplete.');
        }

        $mimeType = $file->getMimeType(); // finfo-based, not client-supplied
        if (!in_array($mimeType, self::ALLOWED_PDF_MIMES, true)) {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        // Magic-byte verification: valid PDFs begin with "%PDF"
        $handle = fopen($file->getRealPath(), 'rb');
        $magic  = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            throw new ApplicationException('The uploaded file is not a valid PDF document.');
        }

        // Remove old attachment before saving new one
        if ($submission->paper_file) {
            $submission->paper_file->delete();
        }

        $submission->paper_file = $file;
        $submission->save();

        $submission->logActivity('file_replaced', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
        ]);
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

    protected function sanitize(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    /** Keep only digits, +, spaces, hyphens, and parentheses. */
    protected function sanitizePhone(mixed $value): string
    {
        return preg_replace('/[^0-9+()\ \-]/', '', (string) $value);
    }
}
