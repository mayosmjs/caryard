<?php namespace Majos\Caryard\Models;

use Model;

class Tenant extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    public $table = 'majos_caryard_tenants';
    public $timestamps = false;

    protected $fillable = [
        'name', 'slug', 'country_code', 'currency', 'currency_symbol', 'locale', 'is_active',
        'banner_title', 'banner_subtitle', 'banner_tag', 'banner_description', 
        'banner_button_text', 'banner_button_url', 'banner_enabled',
        'loan_enabled', 'loan_terms', 'loan_default_term', 
        'loan_min_down_payment_percent', 'loan_max_down_payment_percent', 'loan_annual_rate'
    ];

    public $rules = [
        'name' => 'required'
    ];

    public $hasMany = [
        'locations'     => ['Majos\Caryard\Models\Location'],
        'vehicles'      => ['Majos\Caryard\Models\Vehicle'],
        'divisions'     => ['Majos\Caryard\Models\AdministrativeDivision'],
        'divisionTypes' => ['Majos\Caryard\Models\DivisionType'],
    ];

    public $attachOne = [
        'logo' => ['System\Models\File', 'public' => true],
        'banner_image' => ['System\Models\File', 'public' => true],
    ];

    /**
     * Get the division type labels for this tenant.
     *
     * @return \Illuminate\Support\Collection  [level => DivisionType]
     */
    public function getDivisionTypes()
    {
        return DivisionType::forTenant($this->id);
    }

    /**
     * Get root-level administrative divisions for this tenant.
     *
     * @return array  [id => name]
     */
    public function getRootDivisions()
    {
        return AdministrativeDivision::getRootOptions($this->id);
    }

    /**
     * Get the currency symbol for this tenant from the database.
     * Falls back to default symbols if currency_symbol is not set.
     *
     * @return string
     */
    public function getCurrencySymbol()
    {
        // If currency_symbol is set in database, use it
        // Using array_key_exists to safely check if the attribute exists in model
        if (array_key_exists('currency_symbol', $this->getAttributes()) && !empty($this->currency_symbol)) {
            return $this->currency_symbol;
        }

        // Fallback to default symbols based on currency code
        $symbols = [
            'DZD' => 'DA ', 'AOA' => 'Kz ', 'XOF' => 'CFA ', 'BWP' => 'P ',
            'BIF' => 'FBu ', 'CVE' => '$ ', 'XAF' => 'FCFA ', 'CDF' => 'FC ',
            'DJF' => 'Fdj ', 'EGP' => 'E£ ', 'ERN' => 'Nfk ', 'SZL' => 'L ',
            'ETB' => 'Br ', 'GMD' => 'D ', 'GHS' => 'GH₵ ', 'GNF' => 'FG ',
            'KES' => 'KSh ', 'LSL' => 'L ', 'LRD' => '$ ', 'LYD' => 'LD ',
            'MGA' => 'Ar ', 'MWK' => 'MK ', 'MRU' => 'UM ', 'MUR' => 'Rs ',
            'MAD' => 'DH ', 'MZN' => 'MT ', 'NAD' => '$ ', 'NGN' => '₦ ',
            'RWF' => 'FRw ', 'STN' => 'Db ', 'SCR' => '₨ ', 'SLL' => 'Le ',
            'SOS' => 'S ', 'ZAR' => 'R ', 'SSP' => '£ ', 'SDG' => 'ج.س. ',
            'TZS' => 'TSh ', 'TND' => 'DT ', 'UGX' => 'USh ', 'ZMW' => 'ZK ',
            'ZWL' => '$ ', 'AED' => 'د.إ ', 'AFN' => '؋ ', 'ALL' => 'L ',
            'AMD' => '֏ ', 'ANG' => 'ƒ ', 'AOA' => 'Kz ', 'ARS' => '$ ',
            'AUD' => 'A$ ', 'AWG' => 'ƒ ', 'AZN' => '₼ ', 'BAM' => 'KM ',
            'BBD' => '$ ', 'BDT' => '৳ ', 'BGN' => 'лв ', 'BHD' => '.د.ب ',
            'BIF' => 'FBu ', 'BMD' => '$ ', 'BND' => '$ ', 'BOB' => 'Bs. ',
            'BRL' => 'R$ ', 'BSD' => '$ ', 'BTN' => 'Nu. ', 'BWP' => 'P ',
            'BYN' => 'Br ', 'BZD' => '$ ', 'CAD' => 'C$ ', 'CDF' => 'FC ',
            'CHF' => 'CHF ', 'CLP' => '$ ', 'CNY' => '¥ ', 'COP' => '$ ',
            'CRC' => '₡ ', 'CUP' => '$ ', 'CVE' => '$ ', 'CZK' => 'Kč ',
            'DJF' => 'Fdj ', 'DKK' => 'kr ', 'DOP' => '$ ', 'DZD' => 'DA ',
            'EGP' => 'E£ ', 'ERN' => 'Nfk ', 'ETB' => 'Br ', 'EUR' => '€ ',
            'FJD' => '$ ', 'FKP' => '£ ', 'GBP' => '£ ', 'GEL' => '₾ ',
            'GGP' => '£ ', 'GHS' => 'GH₵ ', 'GIP' => '£ ', 'GMD' => 'D ',
            'GNF' => 'FG ', 'GTQ' => 'Q ', 'GYD' => '$ ', 'HKD' => 'HK$ ',
            'HNL' => 'L ', 'HRK' => 'kn ', 'HTG' => 'G ', 'HUF' => 'Ft ',
            'IDR' => 'Rp ', 'ILS' => '₪ ', 'IMP' => '£ ', 'INR' => '₹ ',
            'IQD' => 'ع.د ', 'IRR' => '﷼ ', 'ISK' => 'kr ', 'JEP' => '£ ',
            'JMD' => '$ ', 'JOD' => 'د.ا ', 'JPY' => '¥ ', 'KES' => 'KSh ',
            'KGS' => 'лв ', 'KHR' => '៛ ', 'KMF' => 'CF ', 'KPW' => '₩ ',
            'KRW' => '₩ ', 'KWD' => 'د.ك ', 'KYD' => '$ ', 'KZT' => '₸ ',
            'LAK' => '₭ ', 'LBP' => 'ل.ل ', 'LKR' => '₨ ', 'LRD' => '$ ',
            'LSL' => 'L ', 'LYD' => 'LD ', 'MAD' => 'د.م. ', 'MDL' => 'L ',
            'MGA' => 'Ar ', 'MKD' => 'ден ', 'MMK' => 'K ', 'MNT' => '₮ ',
            'MOP' => 'P ', 'MRU' => 'UM ', 'MUR' => 'Rs ', 'MVR' => 'ރ. ',
            'MWK' => 'MK ', 'MXN' => '$ ', 'MYR' => 'RM ', 'MZN' => 'MT ',
            'NAD' => '$ ', 'NGN' => '₦ ', 'NIO' => 'C$ ', 'NOK' => 'kr ',
            'NPR' => '₨ ', 'NZD' => '$ ', 'OMR' => 'ر.ع. ', 'PAB' => 'B/. ',
            'PEN' => 'S/. ', 'PGK' => 'K ', 'PHP' => '₱ ', 'PKR' => '₨ ',
            'PLN' => 'zł ', 'PYG' => '₲ ', 'QAR' => 'ر.ق ', 'RON' => 'lei ',
            'RSD' => 'дин. ', 'RUB' => '₽ ', 'RWF' => 'FRw ', 'SAR' => 'ر.س ',
            'SBD' => '$ ', 'SCR' => '₨ ', 'SDG' => 'ج.س. ', 'SEK' => 'kr ',
            'SGD' => '$ ', 'SHP' => '£ ', 'SLL' => 'Le ', 'SOS' => 'S ',
            'SRD' => '$ ', 'SSP' => '£ ', 'STN' => 'Db ', 'SYP' => '£S ',
            'SZL' => 'L ', 'THB' => '฿ ', 'TJS' => 'SM ', 'TMT' => 'T ',
            'TND' => 'DT ', 'TOP' => 'T$ ', 'TRY' => '₺ ', 'TTD' => '$ ',
            'TVD' => '£ ', 'TWD' => 'NT$ ', 'TZS' => 'TSh ', 'UAH' => '₴ ',
            'UGX' => 'USh ', 'USD' => '$ ', 'UYU' => '$ ', 'UZS' => 'лв ',
            'VES' => 'Bs. ', 'VND' => '₫ ', 'VUV' => 'VT ', 'WST' => 'T ',
            'XAF' => 'FCFA ', 'XCD' => '$ ', 'XOF' => 'CFA ', 'XPF' => '₣ ',
            'YER' => '﷼ ', 'ZAR' => 'R ', 'ZMW' => 'ZK ', 'ZWL' => '$ ',
        ];

        return $symbols[$this->currency] ?? $this->currency . ' ';
    }

    /**
     * Check if banner is enabled.
     */
    public function isBannerEnabled()
    {
        return (bool) ($this->banner_enabled ?? true);
    }

    /**
     * Get the banner title with fallback.
     */
    public function getBannerTitle()
    {
        return $this->banner_title ?? 'Premium Automotive Marketplace';
    }

    /**
     * Get the banner subtitle with fallback.
     */
    public function getBannerSubtitle()
    {
        return $this->banner_subtitle ?? '';
    }

    /**
     * Get the banner tag with fallback.
     */
    public function getBannerTag()
    {
        return $this->banner_tag ?? date('Y') . ' Fleet Available';
    }

    /**
     * Get the banner description with fallback.
     */
    public function getBannerDescription()
    {
        return $this->banner_description ?? 'The most trusted destination for luxury vehicle rentals and expert maintenance.';
    }

    /**
     * Get the banner image URL with fallback.
     * Checks for uploaded image first, then falls back to default from component assets.
     */
    public function getBannerImageUrl()
    {
        if ($this->banner_image && $this->banner_image->getPath()) {
            return $this->banner_image->getPath();
        }
        // Fallback to default banner image from component assets
        return url('/plugins/majos/caryard/assets/images/banner.png');
    }

    /**
     * Get the banner button text with fallback.
     */
    public function getBannerButtonText()
    {
        return $this->banner_button_text ?? 'Browse Vehicles';
    }

    /**
     * Get the banner button URL with fallback.
     */
    public function getBannerButtonUrl()
    {
        return $this->banner_button_url ?? '/vehicles';
    }

    /**
     * Check if loan feature is enabled for this tenant.
     * Default is true if not set.
     */
    public function isLoanEnabled()
    {
        return (bool) ($this->loan_enabled ?? true);
    }

    /**
     * Get available loan terms (in months).
     */
    public function getLoanTerms()
    {
        if (empty($this->loan_terms)) {
            return [6, 12, 24, 36, 48];
        }
        // Decode JSON if stored as JSON string
        $terms = is_string($this->loan_terms) ? json_decode($this->loan_terms, true) : $this->loan_terms;
        return is_array($terms) ? $terms : [6, 12, 24, 36, 48];
    }

    /**
     * Get the default loan term in months.
     */
    public function getLoanDefaultTerm()
    {
        return $this->loan_default_term ?? 24;
    }

    /**
     * Get minimum down payment percentage.
     */
    public function getLoanMinDownPaymentPercent()
    {
        return (int) ($this->loan_min_down_payment_percent ?? 10);
    }

    /**
     * Get maximum down payment percentage.
     */
    public function getLoanMaxDownPaymentPercent()
    {
        return (int) ($this->loan_max_down_payment_percent ?? 70);
    }

    /**
     * Get annual interest rate (as decimal, e.g., 0.18 for 18%).
     */
    public function getLoanAnnualRate()
    {
        return (float) ($this->loan_annual_rate ?? 0.18);
    }
}
