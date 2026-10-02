<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_from_a_paid_checkout_confirms_the_order(): void
    {
        config()->set('services.paymongo', [
            'base_url' => 'https://api.paymongo.test',
            'secret_key' => 'sk_test_example',
            'payment_method_types' => ['qrph'],
        ]);

        Http::fake([
            'https://api.paymongo.test/v1/checkout_sessions/cs_test_123' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'status' => 'paid',
                        'payments' => [['id' => 'pay_test_123', 'attributes' => ['status' => 'paid']]],
                    ],
                ],
            ], 200),
        ]);

        $customer = Customer::factory()->create();
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create())
            ->stock(5)
            ->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Pending,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        Payment::factory()->for($order)->create([
            'provider' => 'paymongo',
            'provider_checkout_id' => 'cs_test_123',
            'status' => 'pending',
            'amount_tendered' => 0,
            'paid_at' => null,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.payment.success', $order))
            ->assertRedirect(route('shop.account'))
            ->assertSessionHas('status', 'Payment confirmed for '.$order->order_ref.'.');

        $this->assertSame(OrderStatus::Approved, $order->fresh()->status);
        $this->assertSame('paid', $order->payment->status);
        $this->assertSame('pay_test_123', $order->payment->provider_payment_id);
        $this->assertSame(4, $variant->fresh()->stock);
    }
}
