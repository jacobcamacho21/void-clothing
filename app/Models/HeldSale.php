<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSale extends Model
{
    protected $fillable = [
        'reference',
        'cashier_id',
        'customer_id',
        'customer_name',
        'items',
        'item_count',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'item_count' => 'integer',
            'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineCount(): int
    {
        return count($this->items ?? []);
    }
}
