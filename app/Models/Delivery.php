<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'quote_reference',
        'quoted_fee',
        'quote_expires_at',
        'actual_fee',
        'booking_reference',
        'tracking_url',
        'status',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'booked_at',
        'delivered_at',
        'notes',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'quoted_fee' => 'decimal:2',
            'actual_fee' => 'decimal:2',
            'quote_expires_at' => 'datetime',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'booked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
