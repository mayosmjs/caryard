<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Flash;
use Hash;
use Mail;
use Redirect;
use Request;
use Session;
use Str;
use Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use October\Rain\Filesystem\Filesystem;
use Majos\Conference\Models\Submission;
use Log;

class Registration extends ComponentBase
{
    protected $fileStorage;

    public function componentDetails()
    {
        return [
            'name' => 'Conference Registration',
            'description' => 'Accepts and validates conference abstract submissions.',
        ];
    }

    public function defineProperties()
    {
        return [
            'editPage' => [
                'title' => 'Edit page filename',
                'type' => 'string',
                'default' => 'submission-edit',
                'description' => 'The page handle for editing submissions',
            ],
            'useGlobalSettings' => [
                'title' => 'Use global settings',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Use settings from the backend configuration panel',
            ],
            'deadline' => [
                'title' => 'Submission deadline (override)',
                'type' => 'string',
                'default' => '2027-06-30 23:59:59',
                'description' => 'Format: YYYY-MM-DD HH:MM:SS (only used if Use global settings is unchecked)',
            ],
            'maxFileSizeKb' => [
                'title' => 'Max file size KB (override)',
                'type' => 'string',
                'default' => '5120',
                'description' => 'Maximum allowed PDF size (only used if Use global settings is unchecked)',
            ],
            'turnstileSiteKey' => [
                'title' => 'Turnstile site key (override)',
                'type' => 'string',
                'default' => '',
                'description' => 'Cloudflare Turnstile site key (only used if Use global settings is unchecked)',
            ],
            'turnstileSecretKey' => [
                'title' => 'Turnstile secret key (override)',
                'type' => 'string',
                'default' => '',
                'description' => 'Cloudflare Turnstile secret key (only used if Use global settings is unchecked)',
            ],
            'uniqueEmail' => [
                'title' => 'Unique email (override)',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Prevent multiple submissions from same email (only used if Use global settings is unchecked)',
            ],
            'tokenExpiryDays' => [
                'title' => 'Token expiry days (override)',
                'type' => 'string',
                'default' => '30',
                'description' => 'Days until edit link expires (only used if Use global settings is unchecked)',
            ],
        ];
    }

    public function init()
    {
        $this->fileStorage = new Filesystem();
    }

    public function onRun()
    {
        $settings = $this->getSettings();

        $this->page['turnstileSiteKey'] = $this->getTurnstileSiteKey();
        $this->page['submissionDeadline'] = $settings['deadline'];
        $this->page['csrfToken'] = csrf_token();
        $this->page['maxFileSizeBytes'] = $settings['maxFileSizeKb'] * 1024;

        if ($this->getTurnstileSiteKey()) {
            $this->addJs('https://challenges.cloudflare.com/turnstile/v0/api.js', [
                'async' => true,
                'defer' => true,
            ]);
        }
    }

    protected function getSettings(): array
    {
        if ((bool) $this->property('useGlobalSettings')) {
            try {
                $settings = \Majos\Conference\Models\Settings::instance();
                return [
                    'deadline' => $settings->submission_deadline ?: '2027-06-30 23:59:59',
                    'maxFileSizeKb' => (int) ($settings->max_file_size_kb ?: 5120),
                    'turnstileSiteKey' => $settings->turnstile_site_key ?: '',
                    'turnstileSecretKey' => $settings->turnstile_secret_key ?: '',
                    'uniqueEmail' => (bool) $settings->unique_email,
                    'tokenExpiryDays' => (int) ($settings->token_expiry_days ?: 30),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to load settings', ['error' => $e->getMessage()]);
            }
        }

        return [
            'deadline' => $this->property('deadline', '2027-06-30 23:59:59'),
            'maxFileSizeKb' => (int) $this->property('maxFileSizeKb', 5120),
            'turnstileSiteKey' => $this->property('turnstileSiteKey', ''),
            'turnstileSecretKey' => $this->property('turnstileSecretKey', ''),
            'uniqueEmail' => (bool) $this->property('uniqueEmail', true),
            'tokenExpiryDays' => (int) $this->property('tokenExpiryDays', 30),
        ];
    }

    public function onSubmit()
    {
        try {
            // $this->guardRateLimit();
            $this->guardDeadline();
            // $this->guardTurnstile();

            $data = $this->validateAndSanitizeData();
            $plainPassword = $this->generatePassword();

            $submission = $this->createSubmission($data, $plainPassword);
            $this->processFileUpload($submission);
            $this->sendConfirmationEmail($submission, $plainPassword);

            Session::flash('submission_success', true);
            Flash::success('Your abstract was submitted successfully. Please check your email for the edit link and password.');

            return Redirect::refresh();
        } catch (ValidationException $e) {
            Log::warning('Submission validation failed', [
                'errors' => $e->getMessage(),
                'ip' => Request::ip(),
            ]);
            throw $e;
        } catch (ApplicationException $e) {
            Log::warning('Submission blocked', [
                'reason' => $e->getMessage(),
                'ip' => Request::ip(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Submission error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => Request::ip(),
            ]);
            throw new ApplicationException('An unexpected error occurred. Please try again later.');
        }
    }

    protected function validateAndSanitizeData(): array
    {
        $settings = $this->getSettings();
        $maxFileSizeKb = $settings['maxFileSizeKb'];
        $emailRule = 'required|email|max:255';

        if ($settings['uniqueEmail']) {
            $emailRule .= '|unique:majos_conference_submissions,email';
        }

        $payload = request()->all();
        $payload['paper_file'] = request()->file('paper_file');

        $validator = Validator::make($payload, [
            'fname' => 'required|string|max:120',
            'lname' => 'required|string|max:120',
            'email' => $emailRule,
            'title' => 'required|string|max:255',
            'authors' => 'required|string|max:2000',
            'affiliation' => 'required|string|max:2000',
            'presentation_type' => 'required|string|in:' . implode(',', Submission::PRESENTATION_TYPES),
            'category' => 'required|string|in:' . implode(',', Submission::CATEGORIES),
            'abstract' => 'required|string|min:40|max:6000',
            'department' => 'required|string|max:255',
            'institution' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|in:' . implode(',', Submission::VALID_COUNTRIES),
            'paper_file' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:' . $maxFileSizeKb,
            'keyword1' => 'required|string|max:80',
            'keyword2' => 'required|string|max:80',
            'keyword3' => 'required|string|max:80',
            'keyword4' => 'nullable|string|max:80',
            'keyword5' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $abstract = trim(request('abstract'));

        // Build structured abstract if not provided
        if (!$abstract) {
            $abstract = $this->buildStructuredAbstract();
        }

        return [
            'author_name' => $this->sanitize(request('fname') . ' ' . request('lname')),
            'email' => strtolower($this->sanitize(request('email'))),
            'affiliation' => $this->sanitize(request('affiliation')),
            'department' => $this->sanitize(request('department')),
            'institution' => $this->sanitize(request('institution')),
            'city' => $this->sanitize(request('city')),
            'country' => $this->sanitize(request('country')),
            'phone' => $this->sanitizePhone(implode(' ', array_filter([
                request('tel1'), request('tel2'), request('tel3'), request('tel4'),
            ]))),
            'mobile' => $this->sanitizePhone(implode(' ', array_filter([
                request('pcs1'), request('pcs2'), request('pcs3'), request('pcs4'),
            ]))),
            'title' => $this->sanitize(request('title')),
            'authors' => $this->sanitize(request('authors')),
            'presentation_type' => $this->sanitize(request('presentation_type')),
            'category' => $this->sanitize(request('category')),
            'abstract' => $this->sanitize($abstract),
            'keywords' => $this->collectKeywords(),
        ];
    }

    protected function buildStructuredAbstract(): string
    {
        $parts = array_filter([
            'Purpose / Background: ' . request('aim'),
            'Materials and Methods: ' . request('meth'),
            'Results: ' . request('resu'),
            'Conclusions: ' . request('conc'),
        ]);

        return implode("\n\n", $parts) ?: 'Abstract not provided.';
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

    protected function createSubmission(array $data, string $plainPassword): Submission
    {
        $settings = $this->getSettings();

        $submission = new Submission();
        $submission->fill($data);
        $submission->token = Str::random(64);
        $submission->password_hash = Hash::make($plainPassword);
        $submission->token_expires_at = now()->addDays($settings['tokenExpiryDays']);
        $submission->status = Submission::STATUS_SUBMITTED;
        $submission->submitter_ip = Request::ip();
        $submission->save();

        $submission->logActivity('submission_created', [
            'ip' => Request::ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $submission;
    }

    protected function processFileUpload(Submission $submission): void
    {
        $file = request()->file('paper_file');

        if (!$file) {
            return;
        }

        // Validate file with October's filesystem
        if (!$file->isValid()) {
            throw new ApplicationException('The uploaded file is corrupted or incomplete.');
        }

        // Additional MIME validation
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'application/pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new ApplicationException('Only PDF files are accepted.');
        }

        // Validate PDF authenticity using PDF parser
        try {
            $pdfParser = new \Smalot\PdfParser\Parser();
            $pdfContent = file_get_contents($file->getRealPath());
            $pdfParser->parseContent($pdfContent);
        } catch (\Exception $e) {
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

    protected function sendConfirmationEmail(Submission $submission, string $password): void
    {
        $editUrl = $this->controller->pageUrl($this->property('editPage'), [
            'token' => $submission->token,
        ]);

        $params = [
            'submission' => $submission,
            'password' => $password,
            'editUrl' => $editUrl,
            'expiryDate' => $submission->token_expires_at->format('F j, Y H:i'),
        ];

        try {
            Mail::send('majos.conference::mail.submission_received', $params, function ($message) use ($submission) {
                $message->to($submission->email, $submission->author_name);
                $message->subject('AFRIRPA 2027 Submission Received');
            });

            Log::info('Confirmation email sent', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send confirmation email', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - submission is still valid
        }
    }

    protected function guardDeadline(): void
    {
        try {
            $settings = $this->getSettings();
            $deadline = Carbon::parse($settings['deadline']);
            if ($deadline->isPast()) {
                throw new ApplicationException('The abstract submission deadline has passed.');
            }
        } catch (\Exception $e) {
            throw new ApplicationException('Invalid deadline configuration: ' . $e->getMessage());
        }
    }

    protected function guardRateLimit(): void
    {
        $ip = Request::ip();
        $key = 'conference-submit:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new ApplicationException("Too many submission attempts. Please try again in {$retryAfter} seconds.");
        }

        RateLimiter::hit($key, 3600);
    }

    protected function guardTurnstile(): void
    {
        $response = request('cf-turnstile-response');
        $secret = $this->getTurnstileSecretKey();

        if (!$response) {
            throw new ApplicationException('Please complete the bot protection challenge.');
        }

        if (!$secret) {
            Log::warning('Turnstile secret key not configured');
            return;
        }

        try {
            $result = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => Request::ip(),
            ]);

            if (!$result->json('success')) {
                $errorCodes = $result->json('error-codes', []);
                Log::warning('Turnstile verification failed', ['errors' => $errorCodes]);
                throw new ApplicationException('Bot protection verification failed. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('Turnstile error', ['message' => $e->getMessage()]);
            throw new ApplicationException('Bot protection service unavailable. Please try again later.');
        }
    }

    protected function sanitize($value): string
    {
        return trim(strip_tags((string) $value));
    }

    protected function sanitizePhone($value): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $value);
    }

    protected function generatePassword(): string
    {
        return Str::upper(Str::random(6) . Str::random(4, '0123456789'));
    }

    protected function getTurnstileSiteKey(): string
    {
        $settings = $this->getSettings();
        $key = $settings['turnstileSiteKey'];
        return $key ?: env('TURNSTILE_SITE_KEY', '');
    }

    protected function getTurnstileSecretKey(): string
    {
        $settings = $this->getSettings();
        $secret = $settings['turnstileSecretKey'];
        return $secret ?: env('TURNSTILE_SECRET_KEY', '');
    }
}
