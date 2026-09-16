<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Stock movements for an order.
 *
 * Every deduction is a conditional UPDATE guarded by `stock >= quantity`, so
 * two cashiers ringing up the last shirt at the same moment cannot both win.
 */
class InventoryService
{
    /**
     * Take an order's items off the shelf.
     *
     * @throws InsufficientStockException
     */
    public function deductForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->quantity <= 0 || $item->product_variant_id === null) {
                continue;
            }

            $this->deduct($item->product_variant_id, $item->quantity, $item->product_name, $item->product_size);
        }
    }

    /** Put an order's items back on the shelf. */
    public function restoreForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->quantity <= 0 || $item->product_variant_id === null) {
                continue;
            }

            ProductVariant::whereKey($item->product_variant_id)
                ->update(['stock' => DB::raw('stock + '.(int) $item->quantity)]);
        }
    }

    /**
     * Atomically decrement one variant.
     *
     * @throws InsufficientStockException
     */
    public function deduct(int $variantId, int $quantity, ?string $name = null, ?string $size = null): void
    {
        $affected = ProductVariant::whereKey($variantId)
            ->where('stock', '>=', $quantity)
            ->update(['stock' => DB::raw('stock - '.(int) $quantity)]);

        if ($affected === 0) {
            $variant = ProductVariant::with('product')->find($variantId);

            throw new InsufficientStockException(sprintf(
                'Not enough stock for %s (%s). %d requested, %d on hand.',
                $name ?? $variant?->product?->name ?? 'that item',
                $size ?? $variant?->size ?? '-',
                $quantity,
                $variant?->stock ?? 0
            ));
        }
    }

    /**
     * Check a whole basket before touching anything, so a shortage on the last
     * line does not leave the earlier lines already deducted.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $lines
     *
     * @throws InsufficientStockException
     */
    public function assertAvailable(array $lines): void
    {
        $wanted = [];

        foreach ($lines as $line) {
            $id = (int) $line['product_variant_id'];
            $wanted[$id] = ($wanted[$id] ?? 0) + (int) $line['quantity'];
        }

        if ($wanted === []) {
            return;
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($wanted))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($wanted as $id => $quantity) {
            $variant = $variants->get($id);

            if ($variant === null) {
                throw new InsufficientStockException('One of the selected items is no longer in the catalog.');
            }

            if ($variant->stock < $quantity) {
                throw new InsufficientStockException(sprintf(
                    'Not enough stock for %s (%s). %d requested, %d on hand.',
                    $variant->product?->name ?? 'that item',
                    $variant->size,
                    $quantity,
                    $variant->stock
                ));
            }
        }
    }
}
