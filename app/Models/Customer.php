<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A storefront shopper. Authenticated through the `customer` guard, which is
 * kept separate from the staff guard.
 */
class Customer extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'terms_accepted_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function latestAddress(): ?CustomerAddress
    {
        return $this->addresses()->latest('id')->first();
    }
}