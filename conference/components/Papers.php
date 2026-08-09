<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Majos\Conference\Models\PaperSubmission;
use Majos\Conference\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Papers Component
 *
 * Secure multi-step paper submission flow with Cloudflare Turnstile protection.
 */
class Papers extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Paper Submission Form',
            'description' => 'Industrial-grade secure paper submission with Cloudflare Turnstile bot protection',
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->page['turnstile_site_key'] = env('TURNSTILE_SITE_KEY');
    }

    // -------------------------------------------------------------------------
    // Step 1 – Email submission
    // -------------------------------------------------------------------------

    public function onEmailSubmit()
    {
        // Cloudflare Turnstile Verification
        if (!$this->verifyTurnstile()) {
            return $this->renderStep(1, post('email', ''), 'Bot verification failed. Please try again.');
        }

        // Normalize Email
        $email = strtolower(trim(post('email')));
        $ip = Request::ip();

        $validator = Validator::make(['email' => $email], ['email' => 'required|email|max:255']);
        if ($validator->fails()) {
            return $this->renderStep(1, $email, 'Please check the errors below.', null, $validator->messages());
        }

        // IP Throttling (Global per IP)
        $ipLimitKey = 'paper_ip_limit_' . md5($ip);
        $ipAttempts = cache()->get($ipLimitKey, 0);
        if ($ipAttempts >= 5) {
            Log::warning("Papers: IP $ip throttled due to excessive email requests.");
            return $this->renderStep(1, $email, 'Too many requests from this connection. Please try again later.');
        }

        // Improved Rate-limiting: Limit by IP and Email combined
        $rateLimitKey = 'paper_req_' . md5($email . $ip);
        $attempts     = cache()->get($rateLimitKey, 0);

        if ($attempts >= 3) {
            return $this->renderStep(1, $email, 'Too many attempts for this email. Please try again in an hour.');
        }

        // Generate a DB-backed code
        $record = VerificationCode::generate($email);

        Log::info("Papers: Verification code sent to $email (IP: $ip)");

        try {
            Mail::raw(
                "Your paper submission verification code is: {$record->code}\n\nThis code expires in 15 minutes.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Paper Submission – Verification Code');
                }
            );
        } catch (\Exception $e) {
            Log::error('Papers: Mail delivery failed – ' . $e->getMessage());
            $record->delete();
            return $this->renderStep(1, $email, 'Failed to send verification email. Please try again.');
        }

        cache()->put($rateLimitKey, $attempts + 1, now()->addHour());
        cache()->put($ipLimitKey, $ipAttempts + 1, now()->addHour());

        return $this->renderStep(2, $email);
    }

    // -------------------------------------------------------------------------
    // Step 2 – Code verification
    // -------------------------------------------------------------------------

    public function onVerifyCode()
    {
        $email = strtolower(trim(post('email')));
        $code  = strtoupper(trim(post('code')));
        $ip    = Request::ip();

        // Verification Code Brute Force Protection
        $verifyKey = 'verify_attempts_' . md5($email . $ip);
        $attempts  = cache()->get($verifyKey, 0);

        if ($attempts >= 10) {
            Log::warning("Papers: Brute force suspected for $email from IP $ip");
            return $this->renderStep(2, $email, 'Too many invalid attempts. Please request a new code.');
        }

        $record = VerificationCode::findValid($email, $code);

        if (!$record) {
            cache()->put($verifyKey, $attempts + 1, now()->addMinutes(15));
            return $this->renderStep(2, $email, 'Invalid or expired verification code.');
        }

        $record->markUsed();
        cache()->forget($verifyKey);

        // Structured Session Binding
        Session::put('paper_verification', [
            'email'       => $email,
            'verified_at' => now()->timestamp,
            'ip'          => $ip,
            'token'       => Str::uuid()->toString()
        ]);

        Log::info("Papers: Email $email verified successfully (IP: $ip)");

        return $this->renderStep(3, $email);
    }

    // -------------------------------------------------------------------------
    // Step 3 – Final submission
    // -------------------------------------------------------------------------

    public function onPaperSubmit()
    {
        $email = strtolower(trim(post('email')));
        $ip    = Request::ip();
        $state = Session::get('paper_verification');

        if (!$state || $state['email'] !== $email || $state['ip'] !== $ip) {
            Log::warning("Papers: Session mismatch or expired for $email (IP: $ip)");
            return $this->renderStep(1, '', 'Session expired or invalid. Please verify your email again.');
        }

        if (now()->timestamp - $state['verified_at'] > 3600) {
            return $this->renderStep(1, '', 'Verification expired. Please verify again.');
        }

        $data = [
            'full_name'    => post('full_name'),
            'phone_number' => post('phone_number'),
            'country'      => post('country'),
            'paper_file'   => Request::file('paper_file'),
        ];

        $validator = Validator::make($data, [
            'full_name'    => 'required|string|min:2|max:150',
            'phone_number' => 'required|string|min:5|max:30',
            'country'      => 'required|string|min:2|max:100',
            'paper_file'   => 'required|file|mimes:pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->renderStep(3, $email, 'Please check the errors below.', null, $validator->messages());
        }

        $file = Request::file('paper_file');

        // PDF Signature Validation
        try {
            $handle = fopen($file->getRealPath(), 'rb');
            $header = fread($handle, 5);
            fclose($handle);

            if ($header !== '%PDF-') {
                Log::alert("Papers: Malicious file upload attempt from $email");
                return $this->renderStep(3, $email, 'The file uploaded is not a valid PDF document.');
            }
        } catch (\Exception $e) {
            return $this->renderStep(3, $email, 'Could not read the uploaded file.');
        }

        $referenceNumber = strtoupper(Str::random(12));
        $storedFilename  = $referenceNumber . '.pdf';

        try {
            $path = $file->storeAs('papers', $storedFilename);
            if (!$path) throw new \Exception('Disk storage failed');
        } catch (\Exception $e) {
            Log::error('Papers: Storage failure – ' . $e->getMessage());
            return $this->renderStep(3, $email, 'A system error occurred while saving the file.');
        }

        if (PaperSubmission::where('email', $email)->where('submission_date', '>', now()->subMinutes(1))->exists()) {
             Storage::delete('papers/' . $storedFilename);
             return $this->renderStep(3, $email, 'A submission was already received. Please wait a moment.');
        }

        try {
            $submission                    = new PaperSubmission();
            $submission->email             = $email;
            $submission->verified_at       = date('Y-m-d H:i:s', $state['verified_at']);
            $submission->full_name         = trim($data['full_name']);
            $submission->phone_number      = trim($data['phone_number']);
            $submission->country           = trim($data['country']);
            $submission->original_filename = basename($file->getClientOriginalName());
            $submission->stored_filename   = $storedFilename;
            $submission->reference_number  = $referenceNumber;
            $submission->submission_date   = now();
            $submission->status            = 'completed';

            $submission->save();
        } catch (\Exception $ex) {
            Log::error('Papers: DB Save failure – ' . $ex->getMessage());
            Storage::delete('papers/' . $storedFilename);
            return $this->renderStep(3, $email, 'An unexpected system error occurred.');
        }

        Log::info("Papers: Submission $referenceNumber successful for $email");
        Session::forget('paper_verification');

        return $this->renderStep(4, $email, null, $referenceNumber);
    }

    public function onGoBack()
    {
        return $this->renderStep(1, (string) post('email'));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Verify Cloudflare Turnstile Token
     */
    protected function verifyTurnstile(): bool
    {
        Log::info('Turnstile: Debugging POST data: ' . json_encode(post()));
        $token = post('cf-turnstile-response');

        if (!$token) {
            Log::warning('Turnstile: Token is missing from the request.');
            return false;
        }

        try {
            $response = Http::asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret'   => env('TURNSTILE_SECRET_KEY'),
                    'response' => $token,
                    'remoteip' => Request::ip(),
                ]
            );

            if (!$response->ok()) {
                Log::error('Turnstile: Cloudflare API returned error status ' . $response->status());
                return false;
            }

            $result = $response->json();
            $success = (bool) data_get($result, 'success', false);

            if (!$success) {
                Log::warning('Turnstile: Verification failed. Errors: ' . json_encode(data_get($result, 'error-codes', [])));
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Turnstile: Exception during verification – ' . $e->getMessage());
            return false;
        }
    }

    private function renderStep(int $step, string $email, ?string $error = null, ?string $referenceNumber = null, $errors = null): array
    {
        $vars = [
            'step'               => $step,
            'email'              => $email,
            'error'              => $error,
            'errors'             => $errors,
            'reference_number'   => $referenceNumber,
            'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
        ];

        return ['#paper-submission-content' => $this->renderPartial('papers::partials/form', $vars)];
    }
}