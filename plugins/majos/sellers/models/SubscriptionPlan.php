<?php namespace Majos\Sellers\Models;

use Model;

/**
 * SubscriptionPlan Model
 *
 * @method \October\Rain\Database\Relations\HasMany subscriptions
 */
class SubscriptionPlan extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table name
     */
    protected $table = 'majos_sellers_subscription_plans';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required|between:1,100',
        'tier' => 'required|in:trial,basic,premium',
        'vehicle_limit' => 'required|integer|min:0',
        'price_monthly' => 'nullable|numeric|min:0',
        'price_annual' => 'nullable|numeric|min:0',
    ];

    /**
     * @var array Attributes to cast
     */
    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        'is_active' => 'boolean',
        'vehicle_limit' => 'integer',
        'trial_duration_days' => 'integer',
        'sort_order' => 'integer',
        'features' => 'array',
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'subscriptions' => [
            SellerSubscription::class,
            'key' => 'plan_id',
            'delete' => true
        ]
    ];

    /**
     * Scope active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Scope by tier
     */
    public function scopeByTier($query, $tier)
    {
        return $query->where('tier', $tier);
    }

    /**
     * Get trial plan
     */
    public static function getTrialPlan()
    {
        return self::byTier('trial')->active()->first();
    }

    /**
     * Check if plan has unlimited vehicles
     */
    public function hasUnlimitedVehicles()
    {
        return $this->vehicle_limit === 0;
    }

    /**
     * Get features as array
     */
    public function getFeaturesArrayAttribute()
    {
        if (is_array($this->features)) {
            return $this->features;
        }

        if (is_string($this->features)) {
            return json_decode($this->features, true) ?? [];
        }

        return [];
    }

    /**
     * Get formatted monthly price
     */
    public function getFormattedMonthlyPriceAttribute()
    {
        if ($this->price_monthly === null || $this->price_monthly == 0) {
            return 'Free';
        }
        return '$' . number_format($this->price_monthly, 2);
    }

    /**
     * Get formatted annual price
     */
    public function getFormattedAnnualPriceAttribute()
    {
        if ($this->price_annual === null || $this->price_annual == 0) {
            return 'Free';
        }
        return '$' . number_format($this->price_annual, 2);
    }

    /**
     * Get annual savings percentage
     */
    public function getAnnualSavingsPercentAttribute()
    {
        if ($this->price_monthly && $this->price_annual) {
            $monthlyTotal = $this->price_monthly * 12;
            $savings = (($monthlyTotal - $this->price_annual) / $monthlyTotal) * 100;
            return round($savings);
        }
        return 0;
    }

    /**
     * Get options for tier field
     */
    public function getTierOptions()
    {
        return [
            'trial' => 'Trial (Free)',
            'basic' => 'Basic',
            'premium' => 'Premium',
        ];
    }
}