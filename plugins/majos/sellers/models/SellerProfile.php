<?php namespace Majos\Sellers\Models;

use Model;
use October\Rain\Database\Traits\Validation;

/**
 * SellerProfile Model
 */
class SellerProfile extends Model
{
    use Validation;

    /**
     * @var string The database table
     */
    protected $table = 'majos_sellers_profiles';

    /**
     * @var string Primary key
     */
    protected $primaryKey = 'id';

    /**
     * @var bool Use string timestamps
     */
    public $timestamps = true;

    /**
     * @var bool Use UUIDs for primary key
     */
    public $incrementing = false;

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'id',
        'user_id',
        'tenant_id',
        'division_id',
        'is_seller',
        'company_name',
        'phone_number',
        'address',
        'city',
        'country',
        'identification_type',
        'identification_number',
        'tax_id',
        'is_verified_seller',
    ];

    /**
     * @var array Rules
     */
    public $rules = [
        'user_id' => 'required|integer',
        'company_name' => 'nullable|max:255',
        'phone_number' => 'nullable|max:50',
        'identification_type' => 'nullable|in:national_id,passport,dl',
        'identification_number' => 'nullable|max:100',
        'tax_id' => 'nullable|max:100',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user' => ['RainLab\User\Models\User', 'key' => 'user_id'],
        'tenant' => ['Majos\Caryard\Models\Tenant', 'key' => 'tenant_id'],
        'division' => ['Majos\Caryard\Models\AdministrativeDivision', 'key' => 'division_id'],
    ];

    public $hasMany = [
        'subscriptions' => ['Majos\Sellers\Models\SellerSubscription', 'key' => 'seller_id', 'delete' => true],
        'transactions' => ['Majos\Sellers\Models\SubscriptionTransaction'],
    ];

    /**
     * Generate UUID on creation
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the user associated with this profile
     */
    public function user()
    {
        return $this->belongsTo('RainLab\User\Models\User', 'user_id');
    }

    /**
     * Get the tenant associated with this profile
     */
    public function tenant()
    {
        return $this->belongsTo('Majos\Caryard\Models\Tenant', 'tenant_id');
    }

    /**
     * Get the division associated with this profile
     */
    public function division()
    {
        return $this->belongsTo('Majos\Caryard\Models\AdministrativeDivision', 'division_id');
    }

    /**
     * Get the active subscription for this seller
     */
    public function getActiveSubscription()
    {
        return $this->hasOne('Majos\Sellers\Models\SellerSubscription', 'seller_id')
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Get the current subscription plan
     */
    public function getCurrentPlan()
    {
        $subscription = $this->getActiveSubscription;
        if ($subscription && $subscription->plan) {
            return $subscription->plan;
        }
        return null;
    }

    /**
     * Get options for division_id dropdown
     * Depends on tenant_id selection
     */
    public function getDivisionIdOptions()
    {
        $formData = post();
        $tenantId = $formData['tenant_id'] ?? $this->tenant_id ?? null;

        if (!$tenantId) {
            return [];
        }

        return \Majos\Caryard\Models\AdministrativeDivision::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($div) {
                $prefix = str_repeat('— ', $div->level - 1);
                return [$div->id => $prefix . $div->name];
            })
            ->toArray();
    }

    /**
     * Get full name display (user name + company name)
     */
    public function getFullNameAttribute()
    {
        $userName = $this->user ? $this->user->name : 'User ' . $this->user_id;
        $companyName = $this->company_name ? ' - ' . $this->company_name : '';
        return $userName . $companyName;
    }
}