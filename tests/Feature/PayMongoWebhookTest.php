<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayMongoWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_paid_event_approves_the_order_once(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsec_test_example');

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create())
            ->price(350)
            ->stock(5)
            ->create();
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'total_amount' => 495.50,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'unit_price' => 350,
            'quantity' => 1,
            'line_total' => 350,
        ]);
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'paymongo',
            'provider_checkout_id' => 'cs_test_123',
            'status' => 'pending',
            'amount_due' => 495.50,
            'amount_tendered' => 0,
            'paid_at' => null,
        ]);

        $payload = json_encode([
            'data' => [
                'id' => 'evt_test_123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'cs_test_123',
                        'type' => 'checkout_session',
                        'attributes' => [
                            'payments' => [['id' => 'pay_test_123']],
                            'reference_number' => $order->order_ref,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_example');

        $request = $this->postJson(route('webhooks.paymongo'), json_decode($payload, true), [
            'Paymongo-Signature' => "t={$timestamp},te={$signature},li=",
        ]);

        $request->assertOk()->assertJson(['received' => true]);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('pay_test_123', $payment->fresh()->provider_payment_id);
        $this->assertSame(OrderStatus::Approved, $order->fresh()->status);
        $this->assertSame(4, $variant->fresh()->stock);

        $this->postJson(route('webhooks.paymongo'), json_decode($payload, true), [
            'Paymongo-Signature' => "t={$timestamp},te={$signature},li=",
        ])->assertOk();

        $this->assertSame(4, $variant->fresh()->stock);
    }
}
