<?php

namespace App\Services;

use App\Enums\OrderChannel;

/**
 * The single place that turns a list of lines into money.
 *
 * Both channels call this, so a basket priced in the storefront and the same
 * basket rung up at the register agree to the centavo.
 */
class PricingService
{
    /**
     * @param  array<int, array{unit_price: float|string, quantity: int}>  $lines
     * @return array{
     *     subtotal: float,
     *     discount_amount: float,
     *     shipping_fee: float,
     *     tax_amount: float,
     *     total_amount: float,
     *     quantity: int
     * }
     */
    public function totals(array $lines, OrderChannel $channel): array
    {
        $subtotal = 0.0;
        $quantity = 0;

        foreach ($lines as $line) {
            $subtotal += (float) $line['unit_price'] * (int) $line['quantity'];
            $quantity += (int) $line['quantity'];
        }

        $subtotal = $this->round($subtotal);
        $discount = $this->round($this->discountFor($subtotal, $quantity, $channel));
        $shipping = $this->round($this->shippingFor($channel, $quantity));

        $total = $this->round($subtotal - $discount + $shipping);
        $tax = $this->round($this->taxFor($total));

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'shipping_fee' => $shipping,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'quantity' => $quantity,
        ];
    }

    /**
     * Register sales get the bundle discount once the basket reaches the
     * configured quantity. Online orders are priced as advertised.
     */
    public function discountFor(float $subtotal, int $quantity, OrderChannel $channel): float
    {
        if ($channel !== OrderChannel::Pos) {
            return 0.0;
        }

        $threshold = (int) config('void.pos.discount_threshold');
        $rate = (float) config('void.pos.discount_rate');

        if ($threshold <= 0 || $rate <= 0 || $quantity < $threshold) {
            return 0.0;
        }

        return $subtotal * $rate;
    }

    /** Counter sales are handed over in person, so only online orders ship. */
    public function shippingFor(OrderChannel $channel, int $quantity): float
    {
        if ($channel !== OrderChannel::Online || $quantity === 0) {
            return 0.0;
        }

        return (float) config('void.shipping_fee');
    }

    /**
     * VAT. Configured inclusive, so this is the portion of the total that is
     * tax rather than an amount added on top — it is broken out on the receipt
     * and stored for reporting, but never changes what the customer pays.
     */
    public function taxFor(float $total): float
    {
        $rate = (float) config('void.tax.rate');

        if ($rate <= 0 || $total <= 0) {
            return 0.0;
        }

        return config('void.tax.inclusive')
            ? $total - ($total / (1 + $rate))
            : $total * $rate;
    }

    public function discountLabel(): string
    {
        $rate = (float) config('void.pos.discount_rate') * 100;
        $threshold = (int) config('void.pos.discount_threshold');

        return sprintf('Bundle discount %s%% (%d+ items)', rtrim(rtrim(number_format($rate, 1), '0'), '.'), $threshold);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
