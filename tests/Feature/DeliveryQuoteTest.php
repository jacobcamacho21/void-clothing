<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_returns_the_lalamove_shipping_fee(): void
    {
        config()->set('services.lalamove', [
            'base_url' => 'https://rest.sandbox.lalamove.com',
            'api_key' => 'pk_test_example',
            'api_secret' => 'sk_test_example',
            'market' => 'PH',
            'language' => 'en_PH',
            'service_type' => 'MOTORCYCLE',
            'pickup_address' => 'Tangulan Street, Kawit, Cavite',
            'pickup_latitude' => '14.4450',
            'pickup_longitude' => '120.9010',
        ]);

        Http::fake([
            'https://rest.sandbox.lalamove.com/v3/quotations' => Http::response([
                'data' => [
                    'quotationId' => 'quote-checkout-123',
                    'expiresAt' => '2026-10-02T12:05:00.000Z',
                    'priceBreakdown' => ['total' => '145.50', 'currency' => 'PHP'],
                    'distance' => ['value' => '8200'],
                ],
            ], 201),
        ]);

        $customer = Customer::factory()->create();
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create())
            ->price(350)
            ->stock(5)
            ->create();

        $this->actingAs($customer, 'customer')
            ->postJson(route('shop.cart.add'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->actingAs($customer, 'customer')
            ->postJson(route('shop.checkout.shipping-quote'), [
                'recipient_name' => 'Customer',
                'phone' => '+639176123456',
                'street' => 'Ayala Avenue',
                'city' => 'Makati',
                'province' => 'Metro Manila',
                'postal_code' => '1226',
                'country' => 'Philippines',
            ])
            ->assertOk()
            ->assertJson([
                'provider' => 'lalamove',
                'reference' => 'quote-checkout-123',
                'fee' => 145.50,
                'total' => 495.50,
            ]);
    }
}
