<?php namespace Majos\Caryard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Majos\Caryard\Models\LoanApplication;
use Illuminate\Support\Facades\Mail;
use Flash;

class LoanApplications extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Caryard', 'caryard', 'loan_applications');
    }

    public function onAccept($recordId)
    {
        $application = LoanApplication::with(['vehicle', 'tenant'])->find($recordId);
        if (!$application) {
            Flash::error('Application not found.');
            return;
        }

        $application->status = 'accepted';
        $application->save();

        $this->sendEmail($application, 'approved');

        Flash::success('Application has been marked as Accepted and the applicant notified.');
        return redirect()->refresh();
    }

    public function onReject($recordId)
    {
        $application = LoanApplication::with(['vehicle', 'tenant'])->find($recordId);
        if (!$application) {
            Flash::error('Application not found.');
            return;
        }

        $application->status = 'rejected';
        $application->save();

        $this->sendEmail($application, 'rejected');

        Flash::success('Application has been marked as Rejected and the applicant notified.');
        return redirect()->refresh();
    }

    protected function sendEmail($application, $type)
    {
        $data = json_decode($application->application, true);
        $personal = $data['personal'] ?? [];
        $email = $personal['email'] ?? null;
        $name = ($personal['first_name'] ?? '') . ' ' . ($personal['last_name'] ?? '');

        if (!$email) return;

        $subject = $type === 'approved' 
            ? 'Good News! Your Loan Application for ' . $application->vehicle->title . ' has been approved'
            : 'Update regarding your Loan Application for ' . $application->vehicle->title;

        $viewName = $type === 'approved' ? 'majos.caryard::mail.loan_approved' : 'majos.caryard::mail.loan_rejected';

        try {
            Mail::send($viewName, [
                'name'    => $name,
                'vehicle' => $application->vehicle->title,
                'status'  => $type,
            ], function($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send loan application email: ' . $e->getMessage());
        }
    }
}