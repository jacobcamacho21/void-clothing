<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'receipt_number' => sprintf('VD-%06d', fake()->unique()->numberBetween(1, 999999)),
            'issued_at' => now(),
            'print_count' => 0,
        ];
    }
}
