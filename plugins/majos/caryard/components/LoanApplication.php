<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\LoanApplication as LoanApplicationModel;
use Majos\Caryard\Models\Vehicle;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LoanApplication extends ComponentBase
{
    public $vehicle;
    public $activeStep;
    public $errors;

    public function componentDetails()
    {
        return [
            'name'        => 'Loan Application Form',
            'description' => 'Multi-step loan application form linked to a vehicle',
        ];
    }

    public function defineProperties()
    {
        return [
            'car' => [
                'title'       => 'Car Slug',
                'description' => 'The vehicle slug passed from the URL ?car= parameter',
                'default'     => '',
                'type'        => 'string',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Page Load
    // ─────────────────────────────────────────────────────────────────────────

    public function onRun()
    {
        // ------------------------------------------------------------------
        // 1. Resolve the vehicle — OWASP A01/A03/A04
        //    Use ORM with eager-loaded relations; never raw SQL with user input.
        //    Only accept slugs that match strictly safe characters.
        // ------------------------------------------------------------------
        $rawSlug = $this->property('car') ?: request()->query('car', '');

        // Sanitise: allow only alphanumeric, hyphens, underscores (max 120 chars)
        $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $rawSlug);
        $slug = substr($slug, 0, 120);

        if (empty($slug)) {
            $this->page['vehicle']      = null;
            $this->page['vehicleError'] = 'No vehicle selected. Please go back and choose a car to finance.';
            $this->page['activeStep']   = 1;
            $this->page['fieldErrors']  = [];
            return;
        }

        // Look up vehicle — must be active and belong to a valid tenant
        $vehicle = Vehicle::with([
                'brand', 'vehicle_model', 'condition', 'fuel_type',
                'transmission', 'engine_capacity', 'color',  'division', 'tenant',
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$vehicle) {
            Log::warning('[LoanApplication] Vehicle not found or inactive', [
                'slug' => $slug,
                'ip'   => request()->ip(),
            ]);
            $this->page['vehicle']      = null;
            $this->page['vehicleError'] = 'This vehicle is no longer available or the link is invalid.';
            $this->page['activeStep']   = 1;
            $this->page['fieldErrors']  = [];
            return;
        }

        // ------------------------------------------------------------------
        // 2. Store vehicle_id server-side so subsequent AJAX calls
        //    cannot substitute a different vehicle (OWASP A05)
        // ------------------------------------------------------------------
        Session::put('loan_vehicle_id', $vehicle->id);
        Session::put('loan_vehicle_slug', $vehicle->slug);

        $this->vehicle                  = $vehicle;
        $this->page['vehicle']          = $vehicle;
        $this->page['vehicleError']     = null;
        $this->page['activeStep']       = Session::get('loan_application_step', 1);
        $this->page['fieldErrors']      = [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step Validation Handlers (called via AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function onValidateStep1()
    {
        $this->requireVehicleSession();

        $data = $this->sanitisePost(post());

        $validator = Validator::make($data, [
            'first_name'         => 'required|string|max:50',
            'last_name'          => 'required|string|max:50',
            'phone'              => 'required|string|max:20',
            'email'              => 'required|email|max:100',
            'national_id'        => 'required|string|max:20',
            'kra_pin'            => 'nullable|string|max:20',
            'gender'             => 'required|string|in:male,female,other',
            'date_of_birth'      => 'required|date|before:-18 years',
            'nationality_status' => 'required|string|in:citizen,resident,work_permit',
            'nationality'        => 'required|string|size:2',
            'referral_source'    => 'required|string|in:google,facebook,instagram,twitter,friend,other',
        ], [
            'first_name.required'         => 'First name is required',
            'last_name.required'          => 'Last name is required',
            'phone.required'              => 'Phone number is required',
            'email.required'              => 'Email address is required',
            'email.email'                 => 'Please enter a valid email address',
            'national_id.required'        => 'National ID is required',
            'gender.required'             => 'Please select your gender',
            'date_of_birth.required'      => 'Date of birth is required',
            'date_of_birth.before'        => 'You must be at least 18 years old',
            'nationality_status.required' => 'Please select your nationality status',
            'nationality.required'        => 'Please select your nationality',
            'referral_source.required'    => 'Please select how you heard about us',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        Session::put('loan_application_step', 2);
        Session::put('loan_application_step1', $data);

        return ['success' => true, 'nextStep' => 2];
    }

    public function onValidateStep2()
    {
        $this->requireVehicleSession();

        $data = $this->sanitisePost(post());

        $validator = Validator::make($data, [
            'employment_type' => 'required|string|in:salary,business,self_employed',
            'employer_name'   => 'required|string|max:100',
            'monthly_income'  => 'required|numeric|min:1',
        ], [
            'employment_type.required' => 'Please select your employment type',
            'employer_name.required'   => 'Employer/Business name is required',
            'monthly_income.required'  => 'Monthly income is required',
            'monthly_income.min'       => 'Please enter a valid income amount',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        Session::put('loan_application_step', 3);
        Session::put('loan_application_step2', $data);

        return ['success' => true, 'nextStep' => 3];
    }

    public function onValidateStep3()
    {
        $this->requireVehicleSession();

        $data = $this->sanitisePost(post());

        $validator = Validator::make($data, [
            'loan_amount'         => 'required|numeric|min:1',
            'loan_term'           => 'required|integer|in:6,12,24,36,48,60',
            'loan_currency'       => 'sometimes|string|in:KES,NGN,USD',
            'equity_contribution' => 'nullable|numeric|min:0',
            'monthly_payment'     => 'nullable|numeric|min:0',
            'interest_rate'       => 'nullable|numeric|min:0|max:100',
            'interest_rate_type'  => 'nullable|string|in:fixed,floating',
            'residual_percentage' => 'nullable|integer|in:0,10,20,30',
            'repayment_date'      => 'nullable|string|in:1,15,end',
            'roadworthiness'      => 'required|string|in:yes,no',
            'licence_renewal'     => 'required|string|in:yes,no',
            'tint_permit'         => 'required|string|in:yes,no',
            'fees_payment'        => 'required|string|in:upfront,monthly',
        ], [
            'loan_amount.required'    => 'Loan amount is required',
            'loan_amount.min'         => 'Please enter a valid loan amount',
            'loan_term.required'      => 'Please select a loan term',
            'roadworthiness.required' => 'Please answer the roadworthiness question',
            'licence_renewal.required'=> 'Please answer the licence renewal question',
            'tint_permit.required'    => 'Please answer the tint permit question',
            'fees_payment.required'   => 'Please select how you want to pay fees',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        Session::put('loan_application_step', 4);
        Session::put('loan_application_step3', $data);

        return ['success' => true, 'nextStep' => 4];
    }

    public function onSubmitStep4()
    {
        $vehicleId = $this->requireVehicleSession();

        $data = $this->sanitisePost(post());

        $validator = Validator::make($data, [
            'consent_credit' => 'required|accepted',
            'consent_terms'  => 'required|accepted',
            'consent_notice' => 'required|accepted',
        ], [
            'consent_credit.required' => 'Please consent to a credit check',
            'consent_credit.accepted' => 'Please consent to a credit check',
            'consent_terms.required'  => 'Please accept the Terms & Conditions',
            'consent_terms.accepted'  => 'Please accept the Terms & Conditions',
            'consent_notice.required' => 'Please read the Privacy Notice',
            'consent_notice.accepted' => 'Please read the Privacy Notice',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        return $this->saveApplication($data, $vehicleId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Navigation Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function onGoBack()
    {
        $step = (int) post('step', 1);
        if ($step > 1) {
            Session::put('loan_application_step', $step - 1);
        }
        return ['success' => true, 'nextStep' => max(1, $step - 1)];
    }

    public function onResetForm()
    {
        Session::forget([
            'loan_application_step',
            'loan_application_step1',
            'loan_application_step2',
            'loan_application_step3',
            'loan_vehicle_id',
            'loan_vehicle_slug',
        ]);
        Session::put('loan_application_step', 1);
        return ['success' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Internal Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify the vehicle session exists and return the vehicle_id.
     * Abort with a 403 if missing — stops direct AJAX replay attacks (OWASP A01/A07).
     */
    protected function requireVehicleSession(): int
    {
        $vehicleId = Session::get('loan_vehicle_id');

        if (!$vehicleId) {
            Log::warning('[LoanApplication] AJAX call without vehicle session', [
                'ip'      => request()->ip(),
                'handler' => app('router')->currentRouteName(),
            ]);
            // Return a JSON error the frontend can handle gracefully
            throw new \ApplicationException('Session expired or no vehicle selected. Please start your application again.');
        }

        return (int) $vehicleId;
    }

    /**
     * Strip tags from all string inputs (OWASP A03).
     * Numbers and dates are left to the Validator.
     */
    protected function sanitisePost(array $data): array
    {
        $textFields = [
            'first_name', 'last_name', 'phone', 'email', 'national_id',
            'kra_pin', 'employer_name',
        ];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = strip_tags(trim($data[$field]));
            }
        }
        return $data;
    }

    /**
     * Return a consistent validation-failure response.
     */
    protected function validationFailed(\Illuminate\Validation\Validator $validator): array
    {
        $errors = $validator->messages()->toArray();
        $this->page['fieldErrors'] = $errors;
        return ['success' => false, 'errors' => $errors, 'fieldErrors' => $errors];
    }

    /**
     * Assemble and persist the completed application.
     */
    protected function saveApplication(array $data, int $vehicleId): array
    {
        // Pull previously validated step data from session — never trust
        // the final POST to carry all previous steps (OWASP A01/A05).
        $step1 = Session::get('loan_application_step1', []);
        $step2 = Session::get('loan_application_step2', []);
        $step3 = Session::get('loan_application_step3', []);

        $applicationData = [
            'personal' => [
                'first_name'         => $step1['first_name']         ?? '',
                'last_name'          => $step1['last_name']          ?? '',
                'phone'              => $step1['phone']              ?? '',
                'email'              => $step1['email']              ?? '',
                'national_id'        => $step1['national_id']        ?? '',
                'kra_pin'            => $step1['kra_pin']            ?? '',
                'gender'             => $step1['gender']             ?? '',
                'date_of_birth'      => $step1['date_of_birth']      ?? '',
                'nationality_status' => $step1['nationality_status'] ?? '',
                'nationality'        => $step1['nationality']        ?? '',
                'referral_source'    => $step1['referral_source']    ?? '',
            ],
            'employment' => [
                'employment_type' => $step2['employment_type'] ?? '',
                'employer_name'   => $step2['employer_name']   ?? '',
                'monthly_income'  => $step2['monthly_income']  ?? '',
            ],
            'loan' => [
                'loan_currency'       => $step3['loan_currency']       ?? 'KES',
                'equity_contribution' => $step3['equity_contribution'] ?? '',
                'monthly_payment'     => $step3['monthly_payment']     ?? '',
                'interest_rate_type'  => $step3['interest_rate_type']  ?? '',
                'interest_rate'       => $step3['interest_rate']       ?? '',
                'residual_percentage' => $step3['residual_percentage'] ?? '0',
                'loan_term'           => $step3['loan_term']           ?? '',
                'repayment_date'      => $step3['repayment_date']      ?? '',
                'loan_amount'         => $step3['loan_amount']         ?? '',
                'roadworthiness'      => $step3['roadworthiness']      ?? '',
                'licence_renewal'     => $step3['licence_renewal']     ?? '',
                'tint_permit'         => $step3['tint_permit']         ?? '',
                'fees_payment'        => $step3['fees_payment']        ?? '',
                'upfront_items'       => $step3['upfront_items']       ?? [],
            ],
            'submitted_at' => now()->toDateTimeString(),
        ];

        LoanApplicationModel::create([
            'application' => json_encode($applicationData),
            'vehicle_id'  => $vehicleId,
            'tenant_id'   => $this->getTenantId(),
        ]);

        // Clean up session after successful submission
        Session::forget([
            'loan_application_step',
            'loan_application_step1',
            'loan_application_step2',
            'loan_application_step3',
            'loan_vehicle_id',
            'loan_vehicle_slug',
        ]);

        Log::info('[LoanApplication] Application submitted', [
            'vehicle_id' => $vehicleId,
            'email'      => $step1['email'] ?? 'unknown',
        ]);

        return ['success' => true, 'message' => 'Application submitted successfully!'];
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