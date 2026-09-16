<?php

namespace App\Models;

use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'recipient_name',
        'phone',
        'street',
        'city',
        'province',
        'postal_code',
        'country',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Single-line form used on the order desk and on receipts. */
    public function formatted(): string
    {
        return sprintf(
            '%s, %s, %s %s, %s',
            $this->street,
            $this->city,
            $this->province,
            $this->postal_code,
            $this->country
        );
    }
}
