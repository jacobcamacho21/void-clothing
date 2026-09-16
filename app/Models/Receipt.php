<?php

namespace App\Models;

use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'receipt_number',
        'issued_by',
        'issued_at',
        'print_count',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'print_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isReprint(): bool
    {
        return $this->print_count > 1;
    }
}
