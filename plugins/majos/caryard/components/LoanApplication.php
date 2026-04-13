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
        
        return $this->saveApplication($data);
    }

    public function onSubmit()
    {
        $data = post();
        
        $required = [
            'first_name', 'last_name', 'phone', 'email', 'national_id', 
            'gender', 'date_of_birth', 'nationality_status', 'nationality', 
            'referral_source', 'employment_type', 'employer_name', 'monthly_income',
            'loan_amount', 'loan_term', 'consent_credit', 'consent_terms', 'consent_notice'
        ];
        
        $errors = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (!empty($errors)) {
            $this->errors = $errors;
            $this->page['errors'] = $errors;
            return;
        }
        
        return $this->saveApplication($data);
    }

    protected function saveApplication($data)
    {
        $applicationData = [
            'personal' => [
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'national_id' => $data['national_id'] ?? '',
                'kra_pin' => $data['kra_pin'] ?? '',
                'gender' => $data['gender'] ?? '',
                'date_of_birth' => $data['date_of_birth'] ?? '',
                'nationality_status' => $data['nationality_status'] ?? '',
                'nationality' => $data['nationality'] ?? '',
                'referral_source' => $data['referral_source'] ?? '',
            ],
            'employment' => [
                'employment_type' => $data['employment_type'] ?? '',
                'employer_name' => $data['employer_name'] ?? '',
                'monthly_income' => $data['monthly_income'] ?? '',
            ],
            'loan' => [
                'loan_currency' => $data['loan_currency'] ?? 'KES',
                'equity_contribution' => $data['equity_contribution'] ?? '',
                'monthly_payment' => $data['monthly_payment'] ?? '',
                'interest_rate_type' => $data['interest_rate_type'] ?? '',
                'interest_rate' => $data['interest_rate'] ?? '',
                'residual_percentage' => $data['residual_percentage'] ?? '0',
                'loan_term' => $data['loan_term'] ?? '',
                'repayment_date' => $data['repayment_date'] ?? '',
                'loan_amount' => $data['loan_amount'] ?? '',
                'roadworthiness' => $data['roadworthiness'] ?? '',
                'licence_renewal' => $data['licence_renewal'] ?? '',
                'tint_permit' => $data['tint_permit'] ?? '',
                'fees_payment' => $data['fees_payment'] ?? '',
                'upfront_items' => $data['upfront_items'] ?? [],
            ],
            'submitted_at' => now()->toDateTimeString(),
        ];
        
        LoanApplicationModel::create([
            'application' => json_encode($applicationData),
            'tenant_id' => $this->getTenantId(),
        ]);
        
        \Flash::success('Your loan application has been submitted successfully!');
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