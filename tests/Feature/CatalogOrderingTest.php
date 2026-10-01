<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
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

}
