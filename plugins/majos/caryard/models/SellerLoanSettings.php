<?php namespace Majos\Caryard\Models;

use Model;
use RainLab\User\Models\User;

/**
 * SellerLoanSettings Model
 *
 * Stores loan configuration settings for individual sellers
 * Allows each seller to define their own loan calculation parameters
 *
 * @package majos.caryard
 * @author Majos
 */
class SellerLoanSettings extends Model
{
    /**
     * @var string The database table used by the model
     */
    protected $table = 'majos_caryard_seller_loan_settings';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['id', 'user_id', 'timestamps'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'user_id',
        'loan_enabled',
        'loan_terms',
        'loan_default_term',
        'loan_annual_rate',
        'loan_min_down_payment_percent',
        'loan_max_down_payment_percent',
    ];

    /**
     * @var array Casts
     */
    protected $casts = [
        'loan_enabled' => 'boolean',
        'loan_default_term' => 'integer',
        'loan_annual_rate' => 'decimal:4',
        'loan_min_down_payment_percent' => 'decimal:2',
        'loan_max_down_payment_percent' => 'decimal:2',
    ];

    /**
     * @var array Default values
     */
    public $attributes = [
        'loan_enabled' => false,
        'loan_terms' => '[]',
        'loan_default_term' => 0,
        'loan_annual_rate' => 0,
        'loan_min_down_payment_percent' => 0,
        'loan_max_down_payment_percent' => 0,
    ];

    /**
     * Validation rules
     */
    public $rules = [
        'user_id' => 'required|integer|unique:majos_caryard_seller_loan_settings,user_id',
        'loan_enabled' => 'boolean',
        'loan_default_term' => 'integer|min:0',
        'loan_annual_rate' => 'numeric|min:0',
        'loan_min_down_payment_percent' => 'numeric|min:0|max:100',
        'loan_max_down_payment_percent' => 'numeric|min:0|max:100',
    ];

    public $belongsTo = [
        'user' => [User::class, 'foreignKey' => 'user_id'],
    ];


    /**
     * Get loan terms as an array
     */
    public function getLoanTermsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Set loan terms from an array
     */
    public function setLoanTermsAttribute($value)
    {
        $this->attributes['loan_terms'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Check if loan estimator is enabled
     */
    public function isEnabled()
    {
        return (bool) $this->loan_enabled;
    }

    /**
     * Get the available loan terms as an array of integers
     */
    public function getTermsArray()
    {
        $terms = $this->loan_terms;
        return array_map('intval', $terms);
    }

    /**
     * Get the default term or return first available term
     */
    public function getDefaultTerm()
    {
        $terms = $this->getTermsArray();
        
        if ($this->loan_default_term > 0 && in_array($this->loan_default_term, $terms)) {
            return $this->loan_default_term;
        }
        
        return !empty($terms) ? $terms[0] : 24;
    }

    /**
     * Get annual rate as decimal
     */
    public function getAnnualRate()
    {
        return (float) $this->loan_annual_rate;
    }

    /**
     * Get minimum down payment percentage
     */
    public function getMinDownPaymentPercent()
    {
        return (float) $this->loan_min_down_payment_percent;
    }

    /**
     * Get maximum down payment percentage
     */
    public function getMaxDownPaymentPercent()
    {
        return (float) $this->loan_max_down_payment_percent;
    }

    /**
     * Find or create loan settings for a user
     */
    public static function forUser($userId)
    {
        $settings = self::where('user_id', $userId)->first();
        
        if (!$settings) {
            $settings = new self();
            $settings->user_id = $userId;
            $settings->loan_enabled = false;
            $settings->loan_terms = json_encode([3, 6, 12, 18, 24, 36, 48, 60, 72, 84, 96]);
            $settings->loan_default_term = 0;
            $settings->loan_annual_rate = 0;
            $settings->loan_min_down_payment_percent = 0;
            $settings->loan_max_down_payment_percent = 0;
            $settings->save();
        }
        
        return $settings;
    }
}
