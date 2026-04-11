<?php

namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\SellerLoanSettings;
use Illuminate\Support\Facades\Session;

/**
 * Loan Component
 *
 * Loan calculator for vehicle purchases
 */
class Loan extends ComponentBase
{
    public $tenant;
    public $currencySymbol;
    public $loanEnabled;
    public $carValue;
    public $annualRate;
    public $terms;
    public $term;
    public $downPayment;
    public $minDownPaymentPercent;
    public $maxDownPaymentPercent;
    public $minDownPayment;
    public $maxDownPayment;
    public $downPaymentPercent;
    public $loanAmount;
    public $emi;

    public function componentDetails()
    {
        return [
            'name' => 'Loan Component',
            'description' => 'Vehicle loan calculator'
        ];
    }

    public function defineProperties()
    {
        return [
            'carValue' => [
                'title' => 'Car Value',
                'description' => 'Override the car value for the loan calculator',
                'type' => 'string',
                'default' => '4515000',
            ],
            'tenant' => [
                'title' => 'Tenant',
                'description' => 'Specific tenant to use (optional)',
                'type' => 'string',
            ],
        ];
    }

    /**
     * Initial calculation values displayed on page load
     */
    public function onRun()
    {
        $this->loadTenantSettings();
        
        $this->page['tenant'] = $this->tenant;
        $this->page['loanEnabled'] = $this->loanEnabled;
        $this->page['currencySymbol'] = $this->getCurrencySymbol();
        $this->page['carValue'] = $this->carValue;
        $this->page['annualRate'] = $this->annualRate;
        $this->page['terms'] = $this->terms;
        $this->page['term'] = $this->term;
        $this->page['minDownPayment'] = $this->minDownPayment;
        $this->page['maxDownPayment'] = $this->maxDownPayment;
        $this->page['downPayment'] = $this->downPayment;
        $this->page['downPaymentPercent'] = $this->downPaymentPercent;
        $this->page['emi'] = $this->emi;
        $this->page['loanAmount'] = $this->loanAmount;
    }

    /**
     * Calculate loan - triggered by button click
     */
    public function onCalculate()
    {
        // Ensure we have the latest settings (in case called directly without onRun)
        $this->loadTenantSettings();
        
        $term = (int) post('term', $this->term);
        $downPayment = (int) post('downPayment', $this->downPayment);
        
        // Validate term
        if (!in_array($term, $this->terms)) {
            $term = $this->term;
        }
        
        // Clamp down payment to valid range
        $downPayment = max($this->minDownPayment, min($this->maxDownPayment, $downPayment));
        
        // Calculate values
        $loanAmount = max($this->carValue - $downPayment, 0);
        $downPaymentPercent = $this->carValue > 0 ? ($downPayment / $this->carValue) * 100 : 0;
        $emi = $this->calculateEmi($loanAmount, $term, $this->annualRate);
        $totalInterest = ($emi * $term) - $loanAmount;
        
        return [
            'term' => $term,
            'downPayment' => $downPayment,
            'downPaymentPercent' => round($downPaymentPercent, 1),
            'loanAmount' => $loanAmount,
            'emi' => $emi,
            'interest' => max($totalInterest, 0),
            'minDownPayment' => $this->minDownPayment,
            'maxDownPayment' => $this->maxDownPayment,
        ];
    }

    /**
     * Calculate EMI using standard formula
     */
    protected function calculateEmi($principal, $months, $annualRate)
    {
        if ($principal <= 0 || $months <= 0) {
            return 0;
        }
        
        $monthlyRate = $annualRate / 12;
        
        if ($monthlyRate <= 0) {
            return round($principal / $months);
        }
        
        $pow = pow(1 + $monthlyRate, $months);
        $emi = $principal * $monthlyRate * $pow / ($pow - 1);
        
        return round($emi);
    }

    /**
     * Load tenant settings with fallbacks
     */
    protected function loadTenantSettings()
    {
        $this->resolveTenant();
        
        // First check tenant-level loan settings
        $tenantLoanEnabled = $this->tenant ? $this->tenant->isLoanEnabled() : true;
        
        // Then check seller-level loan settings
        $sellerLoanSettings = $this->getSellerLoanSettings();
        $sellerLoanEnabled = $sellerLoanSettings ? $sellerLoanSettings->isEnabled() : true;
        
        // Loan is enabled only if both tenant AND seller have enabled it
        $this->loanEnabled = $tenantLoanEnabled && $sellerLoanEnabled;
        
        // Load settings: prioritize seller settings, fall back to tenant settings
        if ($sellerLoanSettings && $sellerLoanSettings->isEnabled()) {
            // Seller has custom loan settings - use those
            $this->terms = $sellerLoanSettings->getTermsArray();
            $this->term = $sellerLoanSettings->getDefaultTerm();
            $this->minDownPaymentPercent = $sellerLoanSettings->getMinDownPaymentPercent();
            $this->maxDownPaymentPercent = $sellerLoanSettings->getMaxDownPaymentPercent();
            $this->annualRate = $sellerLoanSettings->getAnnualRate();
        } elseif ($this->tenant) {
            // Use tenant settings
            $this->terms = $this->tenant->getLoanTerms();
            $this->term = $this->tenant->getLoanDefaultTerm();
            $this->minDownPaymentPercent = $this->tenant->getLoanMinDownPaymentPercent();
            $this->maxDownPaymentPercent = $this->tenant->getLoanMaxDownPaymentPercent();
            $this->annualRate = $this->tenant->getLoanAnnualRate();
        } else {
            // Default fallback values
            $this->terms = [6, 12, 24, 36, 48];
            $this->term = 24;
            $this->minDownPaymentPercent = 10;
            $this->maxDownPaymentPercent = 70;
            $this->annualRate = 0.18;
        }
        
        // Get car value
        if (isset($this->page['vehicle']) && isset($this->page['vehicle']['price'])) {
            $this->carValue = (int) $this->page['vehicle']['price'];
        } elseif ($this->property('carValue')) {
            $this->carValue = (int) $this->property('carValue');
        }
        
        // Ensure carValue is set (fallback)
        if (!$this->carValue || $this->carValue <= 0) {
            $this->carValue = 4515000; // Default fallback
        }
        
        // Calculate initial values
        $this->minDownPayment = round($this->carValue * ($this->minDownPaymentPercent / 100));
        $this->maxDownPayment = round($this->carValue * ($this->maxDownPaymentPercent / 100));
        $this->downPayment = round(($this->minDownPayment + $this->maxDownPayment) / 2);
        $this->downPaymentPercent = $this->carValue > 0 
            ? ($this->downPayment / $this->carValue) * 100 
            : 0;
        
        $this->loanAmount = max($this->carValue - $this->downPayment, 0);
        $this->emi = $this->calculateEmi($this->loanAmount, $this->term, $this->annualRate);
    }

    /**
     * Get seller loan settings based on vehicle's seller
     * 
     * @return SellerLoanSettings|null
     */
    protected function getSellerLoanSettings()
    {
        // Get the vehicle from the page data to find the seller
        if (isset($this->page['vehicle'])) {
            $vehicleData = $this->page['vehicle'];
            
            // Handle both array and object formats
            if (is_array($vehicleData)) {
                $sellerId = $vehicleData['seller_id'] ?? null;
            } elseif (is_object($vehicleData)) {
                $sellerId = $vehicleData->seller_id ?? null;
            }
            
            // If we found a seller ID from the vehicle, get their loan settings
            if ($sellerId) {
                return SellerLoanSettings::where('user_id', $sellerId)->first();
            }
        }
        
        return null;
    }

    protected function resolveTenant()
    {
        $urlSlug = $this->property('tenant');
        
        if ($urlSlug && !preg_match('/\{/', $urlSlug)) {
            $this->tenant = Tenant::where('slug', $urlSlug)
                ->orWhere('country_code', strtoupper($urlSlug))
                ->first();
        }
        
        if (!$this->tenant && $tenantId = Session::get('caryard_tenant_id')) {
            $this->tenant = Tenant::find($tenantId);
        }
        
        if (!$this->tenant) {
            $this->tenant = $this->detectTenantByGeoIp();
        }
        
        if (!$this->tenant) {
            $this->tenant = Tenant::where('is_active', true)->first();
        }
        
        if ($this->tenant) {
            Session::put('caryard_tenant_id', $this->tenant->id);
        }
    }
    
    protected function detectTenantByGeoIp()
    {
        $ip = request()->ip();
        $cacheKey = 'caryard_geoip_' . md5($ip);
        
        if ($cc = Session::get($cacheKey)) {
            return Tenant::where('country_code', $cc)->where('is_active', true)->first();
        }
        
        $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false, stream_context_create(['http' => ['timeout' => 2]]));
        if ($resp) {
            $data = json_decode($resp, true);
            if (($data['status'] ?? '') === 'success') {
                $cc = strtoupper($data['countryCode'] ?? '');
                Session::put($cacheKey, $cc);
                return Tenant::where('country_code', $cc)->where('is_active', true)->first();
            }
        }
        
        return null;
    }

    public function getCurrencySymbol()
    {
        return $this->tenant ? $this->tenant->getCurrencySymbol() : '$ ';
    }
}
