<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sizes_are_offered_in_shop_order_not_insertion_order(): void
    {
        $product = Product::factory()->create(['name' => 'Alice', 'slug' => 'alice']);

        // Deliberately created out of order.
        foreach (['Extra Large', 'Small', 'Large', 'Medium'] as $size) {
            ProductVariant::factory()->for($product)->size($size)->create();
        }

        $html = $this->get(route('shop.product', $product))->assertOk()->getContent();

        // Read the ids off the size dropdown in the order they are rendered,
        // then map them back to their sizes.
        preg_match_all('/<option value="(\d+)"/', $html, $matches);

        $rendered = ProductVariant::findMany($matches[1])
            ->sortBy(fn ($variant) => array_search($variant->id, $matches[1]))
            ->pluck('size')
            ->values()
            ->all();

        $this->assertSame(
            ['Small', 'Medium', 'Large', 'Extra Large'],
            $rendered,
            'Sizes must read S, M, L, XL down the dropdown.'
        );
    }

    public function test_an_unexpected_size_sorts_last_rather_than_first(): void
    {
        $cashier = User::factory()->staff()->create();

        $product = Product::factory()->create(['name' => 'Alice', 'slug' => 'alice']);
        ProductVariant::factory()->for($product)->size('One Size')->create();
        ProductVariant::factory()->for($product)->size('Small')->create();
        ProductVariant::factory()->for($product)->size('Large')->create();

        $payload = $this->actingAs($cashier)
            ->getJson(route('pos.catalog'))
            ->assertOk()
            ->json('products.0.variants');

        $this->assertSame(
            ['Small', 'Large', 'One Size'],
            array_column($payload, 'size'),
            'A size outside the configured set belongs after the known ones.'
        );
    }
}
