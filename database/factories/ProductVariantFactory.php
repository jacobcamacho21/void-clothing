<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size' => fake()->randomElement(array_keys(config('void.sizes'))),
            'sku' => 'VD-'.Str::upper(Str::random(6)),
            'price' => fake()->randomElement([300, 350, 400]),
            'stock' => 10,
        ];
    }

    public function size(string $size): static
    {
        return $this->state(fn () => ['size' => $size]);
    }

    public function stock(int $stock): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function price(float $price): static
    {
        return $this->state(fn () => ['price' => $price]);
    }
}
