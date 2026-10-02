<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Payments\PayMongoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_paymongo_checkout_session(): void
    {
        config()->set('services.paymongo', [
            'base_url' => 'https://api.paymongo.test/v2',
            'secret_key' => 'sk_test_example',
            'payment_method_types' => ['card', 'gcash'],
        ]);

        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_test_123',
                    ],
                ],
            ], 201),
        ]);

        $order = Order::factory()->create([
            'shipping_fee' => 145.50,
            'total_amount' => 495.50,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'VOID Shirt',
            'product_size' => 'Large',
            'unit_price' => 350,
            'quantity' => 1,
        ]);
        $order->load('items');

        $session = app(PayMongoService::class)->createCheckoutSession($order);

        $this->assertSame('cs_test_123', $session->id);
        $this->assertSame('https://checkout.paymongo.test/cs_test_123', $session->url);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.test/v2/checkout_sessions'
            && $request['data']['attributes']['line_items'][0]['amount'] === 35000
            && $request['data']['attributes']['line_items'][1]['amount'] === 14550
            && $request['data']['attributes']['reference_number'] === $order->order_ref
        );
    }
}
