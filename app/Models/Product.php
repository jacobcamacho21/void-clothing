<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A design in the catalog. The sellable units are its variants (one per size).
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Total units on hand across every size. */
    public function totalStock(): int
    {
        return (int) $this->variants->sum('stock');
    }

    /** Lowest price across sizes — what the catalog grid advertises. */
    public function displayPrice(): float
    {
        return (float) ($this->variants->min('price') ?? 0);
    }

    public function isSoldOut(): bool
    {
        return $this->totalStock() <= 0;
    }

    /**
     * Web path to the product photo, falling back to the shared placeholder
     * when a design has no artwork on disk yet.
     */
    public function imageUrl(): string
    {
        return asset('images/products/'.($this->image ?: 'placeholder.png'));
    }

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
