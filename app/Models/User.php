<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A back-office account: admin or staff. Cashiers sign in with the same
 * account they use for the order desk.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** Name to show in the UI, falling back to the login handle. */
    public function displayName(): string
    {
        return $this->name ?: $this->username;
    }

    /** @return HasMany<Order, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    /** @return HasMany<HeldSale, $this> */
    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class, 'cashier_id');
    }
}
