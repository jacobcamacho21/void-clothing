<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The house format (App\Services\ReferenceService), with a random
            // counter so a factory can mint many without touching the table.
            'order_ref' => 'VD-WEB-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
            'channel' => OrderChannel::Online,
            'status' => OrderStatus::Pending,
            'customer_name' => fake()->name(),
            'subtotal' => 350,
            'discount_amount' => 0,
            'shipping_fee' => 50,
            'tax_amount' => 42.86,
            'total_amount' => 400,
            'payment_method' => PaymentMethod::Digital->value,
            'placed_at' => now(),
        ];
    }

    public function pos(): static
    {
        return $this->state(fn () => [
            'channel' => OrderChannel::Pos,
            'status' => OrderStatus::Completed,
            'shipping_fee' => 0,
            'total_amount' => 350,
            'payment_method' => PaymentMethod::Cash->value,
            'completed_at' => now(),
        ]);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
