<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable unit: one product in one size, with its own price and stock.
 * This is the row the legacy `products` table actually held.
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'size',
        'sku',
        'price',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Short size label (S / M / L / XL) for the POS size chips. */
    public function sizeAbbreviation(): string
    {
        return config('void.sizes')[$this->size] ?? $this->size;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= config('void.low_stock_threshold');
    }

    public function label(): string
    {
        return $this->product?->name.' ('.$this->size.')';
    }

    /** @param Builder<ProductVariant> $query */
    public function scopeInStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }

    /** @param Builder<ProductVariant> $query */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('stock', '<=', config('void.low_stock_threshold'));
    }
}
