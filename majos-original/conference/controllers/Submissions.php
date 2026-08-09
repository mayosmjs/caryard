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
        $this->vars['statusOptions'] = [
            Submission::STATUS_SUBMITTED => 'Submitted',
            Submission::STATUS_ACCEPTED => 'Accepted',
            Submission::STATUS_REJECTED => 'Rejected',
        ];

        // Only allow GET requests (read-only view)
        // AJAX POST requests for onAccept/onReject are handled separately
        if (Request::isMethod('post') && !Request::ajax()) {
            throw new ApplicationException('This view is read-only. Use Accept/Reject buttons to change status.');
        }

        return $this->asExtension('FormController')->update($id);
    }

    public function preview($id)
    {
        $this->pageTitle = 'Submission Preview';
        $this->vars['statusOptions'] = [
            Submission::STATUS_SUBMITTED => 'Submitted',
            Submission::STATUS_ACCEPTED => 'Accepted',
            Submission::STATUS_REJECTED => 'Rejected',
        ];

        return $this->asExtension('FormController')->preview($id);
    }

    public function onAccept()
    {
        $id = post('id');
        return $this->setDecision($id, Submission::STATUS_ACCEPTED);
    }

    public function onReject()
    {
        $id = post('id');
        return $this->setDecision($id, Submission::STATUS_REJECTED);
    }

    protected function setDecision($id, string $status)
    {
        if (!Request::isMethod('post')) {
            return Redirect::to(Backend::url('majos/conference/submissions/update/' . $id));
        }

        try {
            $submission = Submission::findOrFail($id);

            if (!$submission->canBeDecided()) {
                throw new ApplicationException('This submission cannot be updated at this time.');
            }

            $oldStatus = $submission->status;
            $submission->status = $status;
            $submission->decided_at = now();

            if ($user = BackendAuth::getUser()) {
                $submission->decided_by = $user->id;
            }

            $submission->save();

            $this->sendDecisionEmail($submission, $status);
            $submission->logActivity('status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'decided_by' => $submission->decided_by,
                'admin_id' => optional($user)->id,
            ]);

            $statusLabel = $status === Submission::STATUS_ACCEPTED ? 'accepted' : 'rejected';
            Flash::success("Submission marked as {$statusLabel} and the author was notified.");

        } catch (\Exception $e) {
            Log::error('Decision error', [
                'message' => $e->getMessage(),
                'submission_id' => $id,
                'user_id' => optional(BackendAuth::getUser())->id,
            ]);
            Flash::error('Failed to update submission status: ' . $e->getMessage());
        }

        return Redirect::to(Backend::url('majos/conference/submissions'));
    }

    protected function sendDecisionEmail(Submission $submission, string $status): void
    {
        try {
            Mail::send('majos.conference::mail.decision_notice', [
                'submission' => $submission,
                'status' => $status,
            ], function ($message) use ($submission, $status) {
                $message->to($submission->email, $submission->author_name);
                $message->subject('Your AFRIRPA 2027 submission was ' . $status);
            });

            Log::info('Decision email sent', [
                'submission_id' => $submission->id,
                'status' => $status,
                'email' => $submission->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send decision email', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getStats(): array
    {
        return [
            'total' => Submission::count(),
            'pending' => Submission::submitted()->count(),
            'accepted' => Submission::accepted()->count(),
            'rejected' => Submission::rejected()->count(),
        ];
    }

    public function listExtendView($list)
    {
        $this->addCss('$/majos/conference/assets/css/backend.css');
        $list->addTopPartial($this->makePartial('stats', [
            'stats' => $this->getStats()
        ]));
    }

    public function formExtendFields($form)
    {
        // Make paper_file field optional in form (for updates)
        if ($form->getContext() === 'update') {
            $field = $form->getField('paper_file');
            if ($field) {
                $field->required = false;
            }
        }
    }

    public function formAfterUpdate($model)
    {
        // Log update activity
        $model->logActivity('submission_updated_in_admin', [
            'admin_id' => optional(BackendAuth::getUser())->id,
        ]);
    }
}
