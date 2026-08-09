<?php namespace Majos\Conference\Components;

use Cms\Classes\ComponentBase;
use Flash;
use Hash;
use Log;
use Mail;
use Redirect;
use Request;
use Session;
use Validator;
use Illuminate\Support\Facades\RateLimiter;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use Majos\Conference\Models\Reviewer;
use Majos\Conference\Models\Submission;

/**
 * ReviewPortal Component
 *
 * Provides a secure, token + password protected front-end portal for external
 * reviewers. Reviewers do not require any backend / administrator access.
 *
 * Workflow:
 *  1. Admin assigns submission(s) to a reviewer — reviewer receives an email
 *     with a unique link and a one-time password.
 *  2. Reviewer opens the link, enters their password, and is granted access.
 *  3. Reviewer reads each abstract and submits a decision (Approve / Reject)
 *     with optional comments.
 *  4. Admin is notified and can send the final decision email to the author.
 */
class ReviewPortal extends ComponentBase
{
    private const SESSION_PREFIX    = 'majos_reviewer_auth_';
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_DECAY_S      = 3600; // 1 hour

    // ── Component meta ────────────────────────────────────────────────────

    public function componentDetails(): array
    {
        return [
            'name'        => 'Reviewer Portal',
            'description' => 'Secure front-end abstract review portal. No backend access required.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'adminEmail' => [
                'title'       => 'Admin notification email (override)',
                'description' => 'Email to notify when a review decision is submitted. Falls back to Settings → Notification Email.',
                'type'        => 'string',
                'default'     => '',
            ],
        ];
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────

    public function onRun(): void
    {
        $token = trim((string) get('token', ''));

        // Defaults
        $this->page['token']           = $token;
        $this->page['tokenError']      = null;
        $this->page['isAuthenticated'] = false;
        $this->page['reviewer']        = null;
        $this->page['submissions']     = collect();
        $this->page['pendingCount']    = 0;
        $this->page['reviewedCount']   = 0;

        if (!$token) {
            $this->page['tokenError'] = 'No review token was provided. Please use the link sent to your email.';
            return;
        }

        $reviewer = Reviewer::findByToken($token);

        if (!$reviewer) {
            $this->page['tokenError'] = 'This review link is invalid or has expired. Please contact the conference organisers.';
            return;
        }

        if (!$this->isAuthenticated($reviewer)) {
            // Show password form — do NOT expose reviewer details to the page yet
            return;
        }

        // ── Authenticated ──────────────────────────────────────────────────
        $submissions = Submission::where('reviewer_id', $reviewer->id)
            ->whereIn('status', [
                Submission::STATUS_ASSIGNED_FOR_REVIEW,
                Submission::STATUS_REVIEWED,
            ])
            ->orderBy('assigned_at')
            ->get();

        $reviewer->last_login_at = now();
        $reviewer->saveQuietly();

        $this->page['isAuthenticated'] = true;
        $this->page['reviewer']        = $reviewer;
        $this->page['submissions']     = $submissions;
        $this->page['pendingCount']    = $submissions->where('status', Submission::STATUS_ASSIGNED_FOR_REVIEW)->count();
        $this->page['reviewedCount']   = $submissions->where('status', Submission::STATUS_REVIEWED)->count();
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────

    /**
     * Password login for the reviewer portal.
     */
    public function onLogin()
    {
        $token    = trim((string) post('token', ''));
        $password = (string) post('password', '');

        $limiterKey = 'reviewer-login:' . hash('sha256', $token . '|' . Request::ip());

        if (RateLimiter::tooManyAttempts($limiterKey, self::LOGIN_MAX_ATTEMPTS)) {
            $wait = RateLimiter::availableIn($limiterKey);
            throw new ApplicationException("Too many login attempts. Please try again in {$wait} seconds.");
        }

        $reviewer = Reviewer::findByToken($token);

        if (!$reviewer || !$reviewer->verifyPassword($password)) {
            RateLimiter::hit($limiterKey, self::LOGIN_DECAY_S);
            throw new ValidationException(['password' => 'Incorrect password. Please check your email and try again.']);
        }

        RateLimiter::clear($limiterKey);
        Session::put(self::SESSION_PREFIX . $token, $reviewer->id);

        return Redirect::to(url('/review-portal') . '?token=' . $token);
    }

    /**
     * Sign out from the review portal.
     */
    public function onLogout()
    {
        $token = trim((string) post('token', (string) get('token', '')));
        Session::forget(self::SESSION_PREFIX . $token);
        return Redirect::refresh();
    }

    /**
     * Reviewer submits their approve / reject decision for a single abstract.
     */
    public function onSubmitReview()
    {
        $token    = trim((string) post('token', ''));
        $reviewer = $this->getAuthenticatedReviewer($token);

        if (!$reviewer) {
            throw new ApplicationException('Your session has expired. Please log in again.');
        }

        $submissionId = (int) post('submission_id');
        $decision     = (string) post('decision');
        $feedback     = trim((string) post('feedback', ''));

        $validator = Validator::make(
            ['decision' => $decision, 'submission_id' => $submissionId],
            [
                'decision'      => 'required|in:approved,rejected',
                'submission_id' => 'required|integer|min:1',
            ],
            [
                'decision.required' => 'Please select a decision (Approve or Reject).',
                'decision.in'       => 'Invalid decision value.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $submission = Submission::where('reviewer_id', $reviewer->id)->find($submissionId);

        if (!$submission) {
            throw new ApplicationException('Submission not found or not assigned to you.');
        }

        if (!$submission->canBeReviewed()) {
            throw new ApplicationException('This submission has already been reviewed.');
        }

        $submission->reviewer_decision = $decision;
        $submission->reviewer_feedback = $feedback ?: null;
        $submission->reviewed_at       = now();
        $submission->status            = Submission::STATUS_REVIEWED;
        $submission->save();

        $submission->logActivity('reviewer_decision_submitted', [
            'reviewer_id' => $reviewer->id,
            'decision'    => $decision,
        ]);

        $this->notifyAdmin($submission, $reviewer);

        Flash::success('Your review decision has been submitted. Thank you.');
        $token = trim((string) post('token', ''));
        return Redirect::to(url('/review-portal') . ($token ? '?token=' . $token : ''));
    }

    // ── Session helpers ───────────────────────────────────────────────────

    private function isAuthenticated(Reviewer $reviewer): bool
    {
        return Session::get(self::SESSION_PREFIX . $reviewer->token) === $reviewer->id;
    }

    private function getAuthenticatedReviewer(string $token): ?Reviewer
    {
        if (!$token) {
            return null;
        }
        $reviewerId = Session::get(self::SESSION_PREFIX . $token);
        if (!$reviewerId) {
            return null;
        }
        return Reviewer::where('token', $token)->where('id', $reviewerId)->first();
    }

    // ── Admin notification ────────────────────────────────────────────────

    private function notifyAdmin(Submission $submission, Reviewer $reviewer): void
    {
        $adminEmail = trim((string) $this->property('adminEmail'));

        if (!$adminEmail) {
            try {
                $adminEmail = \Majos\Conference\Models\Settings::instance()->notification_email ?? '';
            } catch (\Throwable $e) {
                // Settings not accessible — continue
            }
        }

        if (!$adminEmail) {
            $admin      = \Backend\Models\User::where('is_superuser', 1)->where('is_activated', 1)->first();
            $adminEmail = $admin ? $admin->email : '';
        }

        if (!$adminEmail) {
            Log::warning('ReviewPortal: no admin email configured for review-decision notifications.', [
                'submission_id' => $submission->id,
            ]);
            return;
        }

        try {
            $adminUrl = url('/backend/majos/conference/submissions/preview/' . $submission->id);

            Mail::send('majos.conference::mail.review_decision_submitted', [
                'submission' => $submission,
                'reviewer'   => $reviewer,
                'adminUrl'   => $adminUrl,
            ], function ($message) use ($adminEmail, $submission) {
                $message->to($adminEmail)
                        ->subject('AFRIRPA 2027 — Reviewer Decision Received: ' . $submission->title);
            });

            Log::info('Admin notified of reviewer decision (portal)', [
                'submission_id' => $submission->id,
                'reviewer_id'   => $reviewer->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to notify admin of reviewer portal decision', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
