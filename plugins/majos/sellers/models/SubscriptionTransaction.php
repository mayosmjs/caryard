<?php namespace Majos\Sellers\Models;

use Model;

/**
 * SubscriptionTransaction Model
 *
 * @property int $id
 * @property int $subscription_id
 * @property string|null $provider
 * @property string|null $transaction_id
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $payment_type
 * @property string|null $customer_details
 * @property string|null $metadata
 * @property string $initiated_by
 * @property string|null $failure_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method \October\Rain\Database\Relations\BelongsTo subscription
 */
class SubscriptionTransaction extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table name
     */
    protected $table = 'majos_sellers_subscription_transactions';

    protected $fillable = [
        'subscription_id',
        'provider',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'payment_type',
        'customer_details',
        'metadata',
        'initiated_by',
        'failure_reason',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Provider constants
     */
    const PROVIDER_MPESA = 'mpesa';
    const PROVIDER_PAYPAL = 'paypal';
    const PROVIDER_STRIPE = 'stripe';

    /**
     * Initiated by constants
     */
    const INITIATED_BY_ADMIN = 'admin';
    const INITIATED_BY_SYSTEM = 'system';
    const INITIATED_BY_FRONTEND = 'frontend';

    /**
     * Payment type constants
     */
    const TYPE_SUBSCRIPTION_CREATE = 'subscription_create';
    const TYPE_SUBSCRIPTION_RENEW = 'subscription_renew';
    const TYPE_SUBSCRIPTION_UPGRADE = 'subscription_upgrade';
    const TYPE_TRIAL_START = 'trial_start';
    const TYPE_FREE_START = 'free_start';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'subscription_id' => 'required|integer|exists:majos_sellers_seller_subscriptions,id',
        'provider' => 'nullable|string|max:50',
        'transaction_id' => 'nullable|string|max:255',
        'amount' => 'required|numeric|min:0',
        'currency' => 'required|string|size:3',
        'status' => 'required|in:pending,completed,failed,refunded',
        'initiated_by' => 'required|in:admin,system,frontend',
    ];

    /**
     * @var array Attributes to cast
     */
    protected $casts = [
        'subscription_id' => 'integer',
        'amount' => 'decimal:2',
        'customer_details' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'subscription' => [SellerSubscription::class, 'key' => 'subscription_id'],
    ];

    /**
     * Get options for subscription_id field
     */
    public function getSubscriptionIdOptions()
    {
        return SellerSubscription::all()->pluck('id', 'id')->toArray();
    }

    /**
     * Scope completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by initiated by
     */
    public function scopeByInitiatedBy($query, $initiatedBy)
    {
        return $query->where('initiated_by', $initiatedBy);
    }

    /**
     * Scope for a specific seller (via subscription -> seller_id)
     */
    public function scopeForSeller($query, $sellerId)
    {
        return $query->whereHas('subscription', function($subQuery) use ($sellerId) {
            $subQuery->where('seller_id', $sellerId);
        });
    }

    /**
     * Find transaction by PayPal capture ID from metadata
     */
    public static function findByCaptureId(string $captureId): ?self
    {
        if (!$captureId) {
            return null;
        }
        
        // Get all pending/completed transactions for this provider
        $transactions = self::whereIn('status', [self::STATUS_PENDING, self::STATUS_COMPLETED])
            ->where('provider', self::PROVIDER_PAYPAL)
            ->get();
        
        // Manually check metadata since it's JSON
        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            if (isset($metadata['capture_id']) && $metadata['capture_id'] === $captureId) {
                return $transaction;
            }
        }
        
        return null;
    }

    /**
     * Find transaction by PayPal order ID from metadata
     */
    public static function findByOrderId(string $orderId): ?self
    {
        if (!$orderId) {
            return null;
        }
        
        // First try direct transaction_id match (most efficient)
        $transaction = self::where('transaction_id', $orderId)
            ->where('provider', self::PROVIDER_PAYPAL)
            ->first();
        
        if ($transaction) {
            return $transaction;
        }
        
        // Fallback: check metadata for order_id
        $transactions = self::whereIn('status', [self::STATUS_PENDING, self::STATUS_COMPLETED])
            ->where('provider', self::PROVIDER_PAYPAL)
            ->get();
        
        foreach ($transactions as $trans) {
            $metadata = $trans->metadata ?? [];
            if (isset($metadata['order_id']) && $metadata['order_id'] === $orderId) {
                return $trans;
            }
        }
        
        return null;
    }

    /**
     * Find transaction by Stripe payment intent ID
     */
    public static function findByPaymentIntentId(string $paymentIntentId): ?self
    {
        if (!$paymentIntentId) {
            return null;
        }
        
        return self::where('transaction_id', $paymentIntentId)
            ->where('provider', self::PROVIDER_STRIPE)
            ->first();
    }

    /**
     * Find pending transaction by provider
     */
    public static function findPendingByProvider(string $provider): ?self
    {
        return self::where('status', self::STATUS_PENDING)
            ->where('provider', $provider)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Find a pending PayPal transaction by the custom_id set at order creation.
     *
     * We store custom_id as 'SUB-{subscription_id}' in the PayPal order's purchase_unit
     * so that PAYMENT.CAPTURE.COMPLETED webhooks can locate the transaction even before
     * the capture_id has been recorded in our metadata.
     */
    public static function findByPayPalCustomId(string $customId): ?self
    {
        if (!$customId || strpos($customId, 'SUB-') !== 0) {
            return null;
        }

        $subscriptionId = intval(substr($customId, 4));
        if (!$subscriptionId) {
            return null;
        }

        // Return the most recent pending (or recently-completed) PayPal transaction
        // for this subscription so the webhook can complete it.
        return self::where('subscription_id', $subscriptionId)
            ->where('provider', self::PROVIDER_PAYPAL)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_COMPLETED])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Find transaction by M-Pesa checkout request ID
     */
    public static function findByCheckoutRequestId(string $checkoutRequestId): ?self
    {
        if (!$checkoutRequestId) {
            return null;
        }
        
        // First try direct transaction_id match
        $transaction = self::where('transaction_id', $checkoutRequestId)
            ->where('provider', self::PROVIDER_MPESA)
            ->first();
        
        if ($transaction) {
            return $transaction;
        }
        
        // Fallback: check metadata for checkout_request_id
        $transactions = self::whereIn('status', [self::STATUS_PENDING, self::STATUS_COMPLETED])
            ->where('provider', self::PROVIDER_MPESA)
            ->get();
        
        foreach ($transactions as $trans) {
            $metadata = $trans->metadata ?? [];
            if (isset($metadata['checkout_request_id']) && $metadata['checkout_request_id'] === $checkoutRequestId) {
                return $trans;
            }
        }
        
        return null;
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction is pending
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction failed
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if transaction is refunded
     */
    public function isRefunded()
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Mark transaction as completed
     */
    public function markAsCompleted($transactionId = null)
    {
        $this->status = self::STATUS_COMPLETED;
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        $this->save();
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed($reason = null)
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->save();
    }

    /**
     * Mark transaction as refunded
     */
    public function markAsRefunded()
    {
        $this->status = self::STATUS_REFUNDED;
        $this->save();
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute()
    {
        return ucfirst($this->status);
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayNameAttribute()
    {
        $names = [
            'mpesa' => 'M-Pesa',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
        ];
        return $names[$this->provider] ?? ucfirst($this->provider ?? 'Unknown');
    }

    /**
     * Get initiated by display name
     */
    public function getInitiatedByDisplayAttribute()
    {
        return ucfirst($this->initiated_by);
    }

    /**
     * Set customer details from array
     */
    public function setCustomerDetailsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['customer_details'] = json_encode($value);
        } else {
            $this->attributes['customer_details'] = $value;
        }
    }

    /**
     * Get customer details as array
     */
    public function getCustomerDetailsAttribute($value)
    {
        if (!$value) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return json_decode($value, true) ?? [];
    }

    /**
     * Set metadata from array
     */
    public function setMetadataAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['metadata'] = json_encode($value);
        } else {
            $this->attributes['metadata'] = $value;
        }
    }

    /**
     * Get metadata as array
     */
    public function getMetadataAttribute($value)
    {
        if (!$value) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return json_decode($value, true) ?? [];
    }
}