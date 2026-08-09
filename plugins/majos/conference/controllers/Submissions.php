<?php namespace Majos\Conference\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Mail;
use Redirect;
use Request;
use BackendAuth;
use Majos\Conference\Models\Submission;
use Majos\Conference\Models\Reviewer;
use Majos\Conference\Models\Settings;
use October\Rain\Exception\ApplicationException;
use Log;

class Submissions extends Controller
{
    public $requiredPermissions = ['majos.conference.manage_submissions'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $pageTitle = 'Conference Submissions';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Conference', 'conference', 'submissions');
        $this->addCss('/plugins/majos/conference/assets/css/backend.css');
    }

    public function update($id)
    {
        $this->pageTitle = 'Submission Details';
        return $this->asExtension('FormController')->update($id);
    }

    public function preview($id)
    {
        $this->pageTitle = 'Submission Preview';
        return $this->asExtension('FormController')->preview($id);
    }

    // ── Review workflow — Admin actions ───────────────────────────────────

    /**
     * Load the "Assign Reviewer" popup form for selected submissions.
     */
    public function onLoadAssignReviewerForm()
    {
        $ids = (array) post('checked', []);

        if (empty($ids)) {
            $single = post('submission_id');
            if ($single) {
                $ids = [(int) $single];
            }
        }

        if (empty($ids)) {
            throw new ApplicationException('Please select at least one submission to assign.');
        }

        return $this->makePartial('assign_reviewer', [
            'ids' => $ids,
        ]);
    }

    /**
     * Assign selected submissions to an external reviewer.
     * Creates the Reviewer record if they do not exist yet; always resets
     * credentials so the email always contains a valid password.
     */
    public function onAssignReviewer()
    {
        $email = strtolower(trim((string) post('reviewer_email', '')));
        $name  = trim((string) post('reviewer_name', ''));
        $ids   = (array) post('submission_ids', []);
        $note  = trim((string) post('reviewer_note', ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApplicationException('Please enter a valid reviewer email address.');
        }

        if (empty($ids)) {
            throw new ApplicationException('No submissions were selected.');
        }

        // Find or create the reviewer
        $reviewer = Reviewer::firstOrNew(['email' => $email]);
        $isNew    = !$reviewer->exists;

        if ($isNew && !$name) {
            $name = $email; // fallback display name
        }
        if ($isNew) {
            $reviewer->name = $name;
        }

        // Always regenerate credentials — reviewer gets fresh link + password
        $plainPassword = $reviewer->generateCredentials();
        $reviewer->save();

        // Assign submissions
        $submissions = Submission::whereIn('id', $ids)
            ->whereIn('status', [
                Submission::STATUS_SUBMITTED,
                Submission::STATUS_ASSIGNED_FOR_REVIEW,
            ])
            ->get();

        if ($submissions->isEmpty()) {
            throw new ApplicationException('None of the selected submissions can be assigned for review at this time.');
        }

        $admin      = BackendAuth::getUser();
        $reviewUrl  = $this->buildReviewPortalUrl($reviewer);

        foreach ($submissions as $submission) {
            $oldStatus = $submission->status;

            $submission->reviewer_id  = $reviewer->id;
            $submission->assigned_at  = now();
            $submission->status       = Submission::STATUS_ASSIGNED_FOR_REVIEW;
            $submission->save();

            $submission->logActivity('assigned_for_review', [
                'reviewer_id'    => $reviewer->id,
                'reviewer_email' => $reviewer->email,
                'old_status'     => $oldStatus,
                'assigned_by'    => optional($admin)->id,
            ]);
        }

        $this->sendReviewAssignedEmail($reviewer, $submissions->all(), $reviewUrl, $plainPassword, $note);

        $count = $submissions->count();
        Flash::success("{$count} submission(s) assigned to {$reviewer->email}. Access credentials sent by email.");

        return Redirect::to(Backend::url('majos/conference/submissions'));
    }

    /**
     * Save the presentation type the submission has been approved for.
     */
    public function onSaveApprovedPresentationType()
    {
        $id   = (int) post('id', (int) post('submission_id'));
        $type = trim((string) post('approved_presentation_type', ''));

        if (!$id) {
            throw new ApplicationException('Submission ID required.');
        }

        $submission = Submission::findOrFail($id);

        if ($type && !in_array($type, Submission::PRESENTATION_TYPES, true)) {
            throw new ApplicationException('Please select a valid presentation type.');
        }

        $submission->approved_presentation_type = $type ?: null;
        $submission->save();

        Flash::success($type
            ? "Approved presentation type set to {$type}."
            : 'Approved presentation type cleared.');

        return Redirect::to(Backend::url('majos/conference/submissions/preview/' . $id));
    }

    /**
     * Send final acceptance to submitter — only available after reviewer has decided.
     */
    public function onAccept()
    {
        return $this->sendFinalDecision((int) post('id'), Submission::STATUS_ACCEPTED);
    }

    /**
     * Send final rejection to submitter — only available after reviewer has decided.
     */
    public function onReject()
    {
        return $this->sendFinalDecision((int) post('id'), Submission::STATUS_REJECTED);
    }

    protected function sendFinalDecision(int $id, string $status)
    {
        try {
            $submission = Submission::findOrFail($id);

            if (!$submission->canReceiveFinalDecision()) {
                throw new ApplicationException(
                    'A final decision cannot be sent yet — this submission must be reviewed first.'
                );
            }

            // Persist the approved presentation type supplied with the request.
            $approvedType = trim((string) post('approved_presentation_type', ''));

            if ($status === Submission::STATUS_ACCEPTED) {
                if (!$approvedType) {
                    throw new ApplicationException(
                        'Please select an approved presentation type before accepting this submission.'
                    );
                }
                if (!in_array($approvedType, Submission::PRESENTATION_TYPES, true)) {
                    throw new ApplicationException('Please select a valid presentation type.');
                }
                $submission->approved_presentation_type = $approvedType;
            }

            $submission->status     = $status;
            $submission->decided_at = now();

            if ($user = BackendAuth::getUser()) {
                $submission->decided_by = $user->id;
            }

            $submission->save();
            $this->sendDecisionEmail($submission, $status);

            $submission->logActivity('final_decision_sent', [
                'new_status'        => $status,
                'decided_by'        => $submission->decided_by,
                'reviewer_decision' => $submission->reviewer_decision,
            ]);

            $label = $status === Submission::STATUS_ACCEPTED ? 'accepted' : 'rejected';
            Flash::success("Submission marked as {$label} and the author has been notified.");

        } catch (ApplicationException $e) {
            Flash::error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Final decision error', [
                'message'       => $e->getMessage(),
                'submission_id' => $id,
            ]);
            Flash::error('Failed to update status: ' . $e->getMessage());
        }

        return Redirect::to(Backend::url('majos/conference/submissions'));
    }

    // ── Email dispatchers ─────────────────────────────────────────────────

    protected function sendReviewAssignedEmail(
        Reviewer $reviewer,
        array $submissions,
        string $reviewUrl,
        string $plainPassword,
        string $note = ''
    ): void {
        try {
            Mail::send('majos.conference::mail.review_assigned', [
                'reviewer'      => $reviewer,
                'submissions'   => $submissions,
                'reviewUrl'     => $reviewUrl,
                'password'      => $plainPassword,
                'note'          => $note,
                'count'         => count($submissions),
            ], function ($message) use ($reviewer) {
                $message->to($reviewer->email, $reviewer->name)
                        ->subject('AFRIRPA 2027 — You Have Been Assigned Abstracts for Review');
            });

            Log::info('Review assignment email sent', [
                'reviewer_id'    => $reviewer->id,
                'reviewer_email' => $reviewer->email,
                'count'          => count($submissions),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send review assignment email', [
                'reviewer_email' => $reviewer->email,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    protected function sendDecisionEmail(Submission $submission, string $status): void
    {
        try {
            $settings = Settings::instance();
            $attachPosterTemplate = $status === Submission::STATUS_ACCEPTED
                && $submission->requiresPosterTemplate();

            Mail::send('majos.conference::mail.decision_notice', [
                'submission' => $submission,
                'status'     => $status,
                'posterTemplate' => $attachPosterTemplate,
                'posterMaxSizeMb' => $settings->posterMaxSizeMb(),
            ], function ($message) use ($submission, $status, $attachPosterTemplate, $settings) {
                $message->to($submission->email, $submission->author_name);
                $message->subject('AFRIRPA 2027 — Your Abstract Submission: ' . ucfirst($status));

                if ($attachPosterTemplate) {
                    $templatePath = $settings->posterTemplatePath();
                    if ($templatePath) {
                        $message->attach($templatePath, [
                            'as'   => $settings->posterTemplateName(),
                            'mime' => $settings->poster_template
                                ? $settings->poster_template->content_type
                                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ]);
                    }
                }
            });

            Log::info('Decision email sent', [
                'submission_id' => $submission->id,
                'status'        => $status,
                'poster_template' => $attachPosterTemplate,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send decision email', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function buildReviewPortalUrl(Reviewer $reviewer): string
    {
        try {
            $path = trim(\Majos\Conference\Models\Settings::instance()->reviewer_portal_page ?? '/review-portal', '/');
        } catch (\Throwable $e) {
            $path = 'review-portal';
        }

        return url($path) . '?token=' . $reviewer->token;
    }

    // ── Stats & list helpers ──────────────────────────────────────────────

    protected function getStats(): array
    {
        return [
            'total'          => Submission::count(),
            'submitted'      => Submission::submitted()->count(),
            'pending_review' => Submission::assignedForReview()->count(),
            'reviewed'       => Submission::reviewed()->count(),
            'accepted'       => Submission::accepted()->count(),
            'rejected'       => Submission::rejected()->count(),
        ];
    }

    public function listExtendView($list)
    {
        $this->addCss('$/majos/conference/assets/css/backend.css');
        $list->addTopPartial($this->makePartial('stats', [
            'stats' => $this->getStats(),
        ]));
    }

    public function formExtendFields($form)
    {
        if ($form->getContext() === 'update') {
            $field = $form->getField('paper_file');
            if ($field) {
                $field->required = false;
            }
        }
    }

    public function formAfterUpdate($model)
    {
        $model->logActivity('submission_updated_in_admin', [
            'admin_id' => optional(BackendAuth::getUser())->id,
        ]);
    }

    public function onDownloadPdf()
    {
        $id = (int) request('id');
        if (!$id) {
            throw new ApplicationException('Invalid request. Submission ID required.');
        }

        $submission = Submission::find($id);
        if (!$submission || !$submission->paper_file) {
            throw new ApplicationException('PDF file not found.');
        }

        $file     = $submission->paper_file;
        $filePath = $file->getLocalPath();

        if (!file_exists($filePath)) {
            throw new ApplicationException('PDF file not found on disk.');
        }

        return \Response::download($filePath, $file->file_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

