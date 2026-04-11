<?php namespace Majos\Sellers\Models;

use Model;
use Exception;

/**
 * SellerSubscription Model
 *
 * @property int $id
 * @property int $seller_id
 * @property int $plan_id
 * @property string $status
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon $expires_at
 * @property bool $auto_renew
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method \October\Rain\Database\Relations\BelongsTo seller
 * @method \October\Rain\Database\Relations\BelongsTo plan
 * @method \October\Rain\Database\Relations\HasMany transactions
 */
class SellerSubscription extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table name
     */
    protected $table = 'majos_sellers_seller_subscriptions';

    protected $fillable = [
        'seller_id',
        'plan_id',
        'status',
        'started_at',
        'expires_at',
        'auto_renew',
        'notes',
    ];

    /**
     * Status constants
     */
    const STATUS_TRIALING = 'trialing';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'seller_id' => 'required|string',
        'plan_id' => 'required|integer|exists:majos_sellers_subscription_plans,id',
        'status' => 'required|in:trialing,active,expired,cancelled',
        'started_at' => 'nullable|date',
        'expires_at' => 'nullable|date',
    ];

    /**
     * @var array Attributes to cast
     */
    protected $casts = [
        'seller_id' => 'string',
        'plan_id' => 'integer',
        'auto_renew' => 'boolean',
    ];

    /**
     * @var array Dates
     */
    protected $dates = [
        'started_at',
        'expires_at',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'seller' => ['Majos\Sellers\Models\SellerProfile', 'key' => 'seller_id'],
        'plan' => [SubscriptionPlan::class, 'key' => 'plan_id'],
    ];

    /**
     * Get options for seller_id field
     * Uses SellerProfile which links to users
     */
    public function getSellerIdOptions()
    {
        $profiles = \Majos\Sellers\Models\SellerProfile::with('user')->get();
        
        if ($profiles->isEmpty()) {
            return [];
        }
        
        return $profiles->mapWithKeys(function($profile) {
            $userName = $profile->user ? $profile->user->name : 'User ' . $profile->user_id;
            $companyName = $profile->company_name ? ' - ' . $profile->company_name : '';
            return [$profile->id => $userName . $companyName];
        })->toArray();
    }

    /**
     * Get options for plan_id field
     */
    public function getPlanIdOptions()
    {
        return SubscriptionPlan::active()->pluck('name', 'id')->toArray();
    }

    public $hasMany = [
        'transactions' => [
            SubscriptionTransaction::class,
            'key' => 'subscription_id',
            'delete' => true
        ]
    ];

    /**
     * Scope active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope trialing subscriptions
     */
    public function scopeTrialing($query)
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    /**
     * Scope expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope expired by date
     */
    public function scopeExpiredByDate($query)
    {
        return $query->where('expires_at', '<', now())->whereNotIn('status', [self::STATUS_EXPIRED]);
    }

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]) 
            && $this->expires_at > now();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->expires_at <= now() || $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if subscription is trialing
     */
    public function isTrialing()
    {
        return $this->status === self::STATUS_TRIALING;
    }

    /**
     * Get remaining days until expiration
     */
    public function getRemainingDays()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Get vehicle limit from plan
     */
    public function getVehicleLimit()
    {
        return $this->plan ? $this->plan->vehicle_limit : 0;
    }

    /**
     * Check if vehicle limit allows adding more
     */
    public function canAddVehicle($currentCount = 0)
    {
        if (!$this->isActive()) {
            return false;
        }

        $limit = $this->getVehicleLimit();
        
        // 0 means unlimited
        if ($limit === 0) {
            return true;
        }

        return $currentCount < $limit;
    }

    /**
     * Check if plan has unlimited vehicles
     */
    public function hasUnlimitedVehicles()
    {
        return $this->plan && $this->plan->hasUnlimitedVehicles();
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute()
    {
        return ucfirst($this->status);
    }

    /**
     * Get formatted expiration date
     */
    public function getFormattedExpiresAtAttribute()
    {
        return $this->expires_at->format('M d, Y');
    }

    /**
     * Mark subscription as expired
     */
    public function markAsExpired()
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    /**
     * Extend subscription
     */
    public function extendSubscription($days)
    {
        $this->expires_at = $this->expires_at->addDays($days);
        
        if ($this->status === self::STATUS_EXPIRED) {
            $this->status = self::STATUS_ACTIVE;
        }
        
        $this->save();
    }

    /**
     * Cancel subscription
     */
    public function cancel()
    {
        $this->status = self::STATUS_CANCELLED;
        $this->auto_renew = false;
        $this->save();
    }

    /**
     * Get the latest successful transaction
     */
    public function getLatestSuccessfulTransaction()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}