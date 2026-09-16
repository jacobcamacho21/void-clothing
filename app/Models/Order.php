<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_ref',
        'channel',
        'status',
        'customer_id',
        'customer_name',
        'cashier_id',
        'subtotal',
        'discount_amount',
        'shipping_fee',
        'tax_amount',
        'total_amount',
        'payment_method',
        'agreed_to_terms',
        'terms_agreed_at',
        'proof_of_payment',
        'shipping_address',
        'notes',
        'cancellation_reason',
        'cancellation_previous_status',
        'cancellation_requested_at',
        'cancellation_review_note',
        'cancellation_reviewed_at',
        'placed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'agreed_to_terms' => 'boolean',
            'terms_agreed_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancellation_reviewed_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_ref';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasOne<Receipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /** Buyer name for display: the walk-in label, or the account username. */
    public function buyerName(): string
    {
        return $this->customer_name
            ?: ($this->customer?->username ?? 'Walk-in Customer');
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isPos(): bool
    {
        return $this->channel === OrderChannel::Pos;
    }

    public function isOnline(): bool
    {
        return $this->channel === OrderChannel::Online;
    }

    /** @param Builder<Order> $query */
    public function scopeOnline(Builder $query): void
    {
        $query->where('channel', OrderChannel::Online);
    }

    /** @param Builder<Order> $query */
    public function scopePos(Builder $query): void
    {
        $query->where('channel', OrderChannel::Pos);
    }

    /** @param Builder<Order> $query */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('status', OrderStatus::Pending);
    }

    /**
     * Orders whose stock is currently committed — used when reporting on what
     * has actually left the shelf.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeHoldingStock(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Approved, OrderStatus::Completed]);
    }
}
