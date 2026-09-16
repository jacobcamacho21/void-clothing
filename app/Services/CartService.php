<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Session;

/**
 * The storefront basket.
 *
 * Kept in the session exactly as the original site did, so a shopper can fill
 * a basket before signing in. Only variant ids and quantities are stored —
 * prices are always re-read from the catalog, never trusted from the browser.
 */
class CartService
{
    private const KEY = 'shop.cart';

    public function __construct(private readonly PricingService $pricing) {}

    /**
     * Add units of a variant, refusing to exceed what is on the shelf.
     *
     * @return array{ok: bool, message?: string}
     */
    public function add(int $variantId, int $quantity = 1): array
    {
        $variant = ProductVariant::with('product')->find($variantId);

        if ($variant === null) {
            return ['ok' => false, 'message' => 'That item is no longer available.'];
        }

        if ($quantity < 1) {
            return ['ok' => false, 'message' => 'Choose at least one unit.'];
        }

        $items = $this->raw();
        $already = $items[$variantId] ?? 0;

        if ($variant->stock < $already + $quantity) {
            return [
                'ok' => false,
                'message' => $variant->stock <= $already
                    ? 'No more stock available for that size.'
                    : sprintf('Only %d left in that size.', $variant->stock),
            ];
        }

        $items[$variantId] = $already + $quantity;
        $this->put($items);

        return ['ok' => true];
    }

    /** Set an exact quantity; zero or less removes the line. */
    public function setQuantity(int $variantId, int $quantity): array
    {
        if ($quantity < 1) {
            $this->remove($variantId);

            return ['ok' => true];
        }

        $variant = ProductVariant::find($variantId);

        if ($variant === null) {
            return ['ok' => false, 'message' => 'That item is no longer available.'];
        }

        if ($variant->stock < $quantity) {
            return ['ok' => false, 'message' => sprintf('Only %d left in that size.', $variant->stock)];
        }

        $items = $this->raw();
        $items[$variantId] = $quantity;
        $this->put($items);

        return ['ok' => true];
    }

    public function remove(int $variantId): void
    {
        $items = $this->raw();
        unset($items[$variantId]);
        $this->put($items);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /**
     * The basket resolved against the live catalog. Lines whose variant has
     * disappeared are dropped; lines that now exceed stock are trimmed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        $items = $this->raw();

        if ($items === []) {
            return [];
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $lines = [];
        $changed = false;

        foreach ($items as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant === null || $variant->product === null) {
                unset($items[$variantId]);
                $changed = true;

                continue;
            }

            if ($quantity > $variant->stock) {
                $quantity = $variant->stock;
                $changed = true;

                if ($quantity < 1) {
                    unset($items[$variantId]);

                    continue;
                }

                $items[$variantId] = $quantity;
            }

            $price = (float) $variant->price;

            $lines[] = [
                'product_variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'product_slug' => $variant->product->slug,
                'product_image' => $variant->product->imageUrl(),
                'product_size' => $variant->size,
                'unit_price' => $price,
                'quantity' => $quantity,
                'line_total' => round($price * $quantity, 2),
                'stock' => $variant->stock,
            ];
        }

        if ($changed) {
            $this->put($items);
        }

        return $lines;
    }

    /**
     * @return array{
     *     subtotal: float, discount_amount: float, shipping_fee: float,
     *     tax_amount: float, total_amount: float, quantity: int
     * }
     */
    public function totals(): array
    {
        return $this->pricing->totals($this->lines(), OrderChannel::Online);
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    /** @return array<int, int> variant id => quantity */
    private function raw(): array
    {
        /** @var array<int, int> $items */
        $items = Session::get(self::KEY, []);

        return $items;
    }

    /** @param array<int, int> $items */
    private function put(array $items): void
    {
        Session::put(self::KEY, $items);
    }
}
