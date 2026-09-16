<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('09#########'),
            'street' => fake()->streetAddress(),
            'city' => 'Kawit',
            'province' => 'Cavite',
            'postal_code' => '4102',
            'country' => 'Philippines',
        ];
    }
}
