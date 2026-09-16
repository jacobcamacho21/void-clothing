<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::Cash->value,
            'amount_due' => 350,
            'amount_tendered' => 500,
            'change_due' => 150,
            'paid_at' => now(),
        ];
    }
}
