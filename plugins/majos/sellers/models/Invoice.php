<?php namespace Majos\Sellers\Models;

use Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice Model
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $subscription_id
 * @property string $invoice_number
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $description
 * @property string|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method BelongsTo transaction
 * @method BelongsTo subscription
 */
class Invoice extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table name
     */
    protected $table = 'majos_sellers_invoices';

    protected $fillable = [
        'transaction_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'description',
        'metadata',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'transaction_id' => 'required|integer|exists:majos_sellers_subscription_transactions,id',
        'subscription_id' => 'required|integer|exists:majos_sellers_seller_subscriptions,id',
        'invoice_number' => 'required|unique:majos_sellers_invoices',
        'amount' => 'required|numeric|min:0',
        'currency' => 'required|string|max:3',
        'status' => 'required|string|in:pending,paid,overdue,cancelled',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'transaction' => [SubscriptionTransaction::class, 'key' => 'transaction_id'],
        'subscription' => [SellerSubscription::class, 'key' => 'subscription_id'],
    ];

    /**
     * Get the seller through subscription
     */
    public function seller()
    {
        return $this->hasOneThrough(
            SellerProfile::class,
            SellerSubscription::class,
        );
    }

    /**
     * Get the associated transaction
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTransaction::class, 'transaction_id');
    }

    /**
     * Get the associated subscription
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SellerSubscription::class, 'subscription_id');
    }

    /**
     * Get the seller name through subscription
     */
    public function getSellerNameAttribute()
    {
        return $this->subscription && $this->subscription->seller ? $this->subscription->seller->name : null;
    }

    /**
     * Get the plan name through subscription
     */
    public function getPlanNameAttribute()
    {
        return $this->subscription && $this->subscription->plan ? $this->subscription->plan->name : null;
    }

    /**
     * Generate a unique invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        // Get the last invoice number for this month
        $lastInvoice = self::where('invoice_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        $sequence = 1;
        if ($lastInvoice) {
            $lastSequence = (int) substr($lastInvoice->invoice_number, -4);
            $sequence = $lastSequence + 1;
        }
        
        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * Create an invoice from a successful transaction
     */
    public static function createFromTransaction(SubscriptionTransaction $transaction): self
    {
        $subscription = $transaction->subscription;
        
        // Get plan name for description
        $planName = 'Subscription';
        if ($subscription && $subscription->plan) {
            $planName = $subscription->plan->name ?? 'Subscription';
        }
        
        return self::create([
            'transaction_id' => $transaction->id,
            'subscription_id' => $transaction->subscription_id,
            'invoice_number' => self::generateInvoiceNumber(),
            'amount' => is_array($transaction->amount) ? ($transaction->amount[0] ?? 0) : $transaction->amount,
            'currency' => is_array($transaction->currency) ? ($transaction->currency[0] ?? 'USD') : $transaction->currency,
            'status' => self::STATUS_PAID,
            'description' => "Payment for {$planName}",
            'metadata' => json_encode([
                'payment_type' => $transaction->payment_type,
                'provider' => $transaction->provider,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(): void
    {
        $this->status = self::STATUS_PAID;
        $this->save();
    }

    /**
     * Mark invoice as overdue
     */
    public function markAsOverdue(): void
    {
        $this->status = self::STATUS_OVERDUE;
        $this->save();
    }

    /**
     * Mark invoice as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }
}