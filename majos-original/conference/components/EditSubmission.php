<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Flash;
use Hash;
use Redirect;
use Request;
use Session;
use Validator;
use Illuminate\Support\Facades\RateLimiter;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use October\Rain\Filesystem\Filesystem;
use Majos\Conference\Models\Submission;
use Log;

class EditSubmission extends ComponentBase
{
    public $submission;

    protected $fileStorage;

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

    public function init()
    {
        $this->fileStorage = new Filesystem();
    }

    public function onRun()
    {
        $this->submission = $this->loadSubmission();
        $this->page['submission'] = $this->submission;
        $this->page['canEditSubmission'] = $this->canEditSubmission();
        $this->page['csrfToken'] = csrf_token();

        $settings = $this->getSettings();
        $this->page['maxFileSizeBytes'] = $settings['maxFileSizeKb'] * 1024;
    }

    protected function getSettings(): array
    {
        if ((bool) $this->property('useGlobalSettings')) {
            try {
                $settings = \Majos\Conference\Models\Settings::instance();
                return [
                    'maxFileSizeKb' => (int) ($settings->max_file_size_kb ?: 5120),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to load settings', ['error' => $e->getMessage()]);
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

            $password = request('password');

            if (!Hash::check((string) $password, $submission->password_hash)) {
                Log::warning('Failed password attempt', [
                    'submission_id' => $submission->id,
                    'ip' => Request::ip(),
                    'token' => $submission->token,
                ]);
                throw new ApplicationException('The password is incorrect.');
            }

            Session::put($this->sessionKey(), $submission->id);
            $submission->logActivity('password_verified', ['ip' => Request::ip()]);

            Flash::success('Submission unlocked. You can now make changes.');

            return Redirect::refresh();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Edit authentication error', [
                'message' => $e->getMessage(),
                'ip' => Request::ip(),
                'token' => $this->param('token'),
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

            $submission->logActivity('submission_updated', ['ip' => Request::ip()]);

            Flash::success('Your submission has been updated successfully.');

            return Redirect::refresh();
        } catch (ValidationException $e) {
            Log::warning('Update validation failed', [
                'errors' => $e->getMessage(),
                'ip' => Request::ip(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Update error', [
                'message' => $e->getMessage(),
                'ip' => Request::ip(),
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
        $ip = Request::ip();
        $key = 'conference-edit:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 8)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new ApplicationException("Too many password attempts. Please try again in {$retryAfter} seconds.");
        }

        RateLimiter::hit($key, 3600);
    }

    protected function validateUpdateData(): array
    {
        $settings = $this->getSettings();
        $maxFileSizeKb = $settings['maxFileSizeKb'];
        $payload = request()->all();
        $payload['paper_file'] = request()->file('paper_file');

        $validator = Validator::make($payload, [
            'email' => 'required|email|max:255',
            'title' => 'required|string|max:255',
            'authors' => 'required|string|max:2000',
            'affiliation' => 'required|string|max:2000',
            'presentation_type' => 'required|string|in:' . implode(',', Submission::PRESENTATION_TYPES),
            'category' => 'required|string|in:' . implode(',', Submission::CATEGORIES),
            'abstract' => 'required|string|min:40|max:6000',
            'paper_file' => 'nullable|file|mimes:pdf|mimetypes:application/pdf|max:' . $maxFileSizeKb,
            'keyword1' => 'required|string|max:80',
            'keyword2' => 'required|string|max:80',
            'keyword3' => 'required|string|max:80',
            'keyword4' => 'nullable|string|max:80',
            'keyword5' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'email' => $this->sanitize(request('email')),
            'title' => $this->sanitize(request('title')),
            'authors' => $this->sanitize(request('authors')),
            'affiliation' => $this->sanitize(request('affiliation')),
            'presentation_type' => $this->sanitize(request('presentation_type')),
            'category' => $this->sanitize(request('category')),
            'abstract' => $this->sanitize(request('abstract')),
            'keywords' => $this->collectKeywords(),
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

        $mimeType = $file->getMimeType();
        if ($mimeType !== 'application/pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        // Delete old file if exists
        if ($submission->paper_file) {
            $submission->paper_file->delete();
        }

        $submission->paper_file = $file;
        $submission->save();

        Log::info('File updated in submission', [
            'submission_id' => $submission->id,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);
    }

    protected function collectKeywords(): array
    {
        $keywords = array_filter([
            request('keyword1'),
            request('keyword2'),
            request('keyword3'),
            request('keyword4'),
            request('keyword5'),
        ]);

        return array_values(array_map(function ($kw) {
            return $this->sanitize($kw);
        }, $keywords));
    }

    protected function sanitize($value): string
    {
        return trim(strip_tags((string) $value));
    }
}
