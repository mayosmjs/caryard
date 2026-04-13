<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\LoanApplication as LoanApplicationModel;
use Illuminate\Support\Facades\Session;

class LoanApplication extends ComponentBase
{
    public $activeStep;
    public $errors;

    public function componentDetails()
    {
        return [
            'name' => 'Loan Application Form',
            'description' => 'Multi-step loan application form',
        ];
    }

    public function onRun()
    {
        $this->activeStep = Session::get('loan_application_step', 1);
        $this->page['activeStep'] = $this->activeStep;
        $this->page['errors'] = $this->errors = [];
    }

    public function onValidateStep1()
    {
        $data = post();
        
        $required = ['first_name', 'last_name', 'phone', 'email', 'national_id', 'gender', 'date_of_birth', 'nationality_status', 'nationality', 'referral_source'];
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (!empty($errors)) {
            $this->errors = $errors;
            $this->page['errors'] = $errors;
            return ['success' => false, 'errors' => $errors];
        }
        
        Session::put('loan_application_step', 2);
        Session::put('loan_application_step1', $data);
        
        return ['success' => true, 'nextStep' => 2];
    }

    public function onValidateStep2()
    {
        $data = post();
        
        $required = ['employment_type', 'employer_name', 'monthly_income'];
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (!empty($errors)) {
            $this->errors = $errors;
            $this->page['errors'] = $errors;
            return ['success' => false, 'errors' => $errors];
        }
        
        Session::put('loan_application_step', 3);
        Session::put('loan_application_step2', $data);
        
        return ['success' => true, 'nextStep' => 3];
    }

    public function onValidateStep3()
    {
        $data = post();
        
        $required = ['loan_amount', 'loan_term'];
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (!empty($errors)) {
            $this->errors = $errors;
            $this->page['errors'] = $errors;
            return ['success' => false, 'errors' => $errors];
        }
        
        Session::put('loan_application_step', 4);
        Session::put('loan_application_step3', $data);
        
        return ['success' => true, 'nextStep' => 4];
    }

    public function onSubmitStep4()
    {
        $data = post();
        
        if (empty($data['consent_credit']) || empty($data['consent_terms']) || empty($data['consent_notice'])) {
            $this->errors = ['Please accept all consent checkboxes'];
            $this->page['errors'] = $this->errors;
            return ['success' => false, 'errors' => $this->errors];
        }
        
        $applicationData = [
            'step1' => Session::get('loan_application_step1', []),
            'step2' => Session::get('loan_application_step2', []),
            'step3' => Session::get('loan_application_step3', []),
            'step4' => $data,
            'submitted_at' => now()->toDateTimeString(),
        ];
        
        LoanApplicationModel::create([
            'application' => json_encode($applicationData),
            'tenant_id' => $this->getTenantId(),
        ]);
        
        Session::forget(['loan_application_step', 'loan_application_step1', 'loan_application_step2', 'loan_application_step3']);
        Session::put('loan_application_step', 1);
        
        \Flash::success('Your loan application has been submitted successfully!');
        
        return ['success' => true, 'message' => 'Application submitted'];
    }

    public function onGoBack()
    {
        $step = (int) post('step', 1);
        if ($step > 1) {
            Session::put('loan_application_step', $step - 1);
        }
        return ['success' => true, 'nextStep' => $step - 1];
    }

    public function onResetForm()
    {
        Session::forget(['loan_application_step', 'loan_application_step1', 'loan_application_step2', 'loan_application_step3']);
        Session::put('loan_application_step', 1);
        
        $this->activeStep = 1;
        $this->page['activeStep'] = 1;
        
        return ['success' => true];
    }

    protected function getTenantId()
    {
        if ($tenantId = Session::get('caryard_tenant_id')) {
            return $tenantId;
        }
        
        $tenant = \Majos\Caryard\Models\Tenant::where('is_active', true)->first();
        return $tenant ? $tenant->id : null;
    }
}