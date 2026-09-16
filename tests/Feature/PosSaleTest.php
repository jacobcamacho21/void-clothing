<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->staff()->create();

        $product = Product::factory()->create(['name' => 'No Signal']);
        $this->variant = ProductVariant::factory()
            ->for($product)
            ->size('Medium')
            ->price(350)
            ->stock(10)
            ->create();
    }

    public function test_the_register_requires_a_signed_in_staff_account(): void
    {
        $this->get(route('pos.register'))->assertRedirect(route('staff.login'));
    }

    public function test_the_register_loads_the_catalog(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('pos.register'))
            ->assertOk()
            ->assertSee('No Signal')
            ->assertSee('Charge Order');
    }

    public function test_a_cash_sale_is_recorded_with_payment_receipt_and_stock_deduction(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 1000,
        ]);

        $response->assertCreated();

        $order = Order::firstOrFail();

        $this->assertSame(OrderChannel::Pos, $order->channel);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame($this->cashier->id, $order->cashier_id);
        $this->assertEquals(700.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->shipping_fee, 'Counter sales must not be charged shipping.');
        $this->assertEquals(700.00, (float) $order->total_amount);

        $this->assertEquals(300.00, (float) $order->payment->change_due);
        $this->assertNotNull($order->receipt);
        $this->assertSame(8, $this->variant->fresh()->stock);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_the_bundle_discount_applies_once_the_basket_reaches_the_threshold(): void
    {
        config(['void.pos.discount_threshold' => 3, 'void.pos.discount_rate' => 0.05]);

        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 3]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 2000,
        ])->assertCreated();

        $order = Order::firstOrFail();

        // 3 x 350 = 1050, less 5% = 997.50
        $this->assertEquals(1050.00, (float) $order->subtotal);
        $this->assertEquals(52.50, (float) $order->discount_amount);
        $this->assertEquals(997.50, (float) $order->total_amount);
    }

    public function test_no_discount_below_the_threshold(): void
    {
        config(['void.pos.discount_threshold' => 3, 'void.pos.discount_rate' => 0.05]);

        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 1000,
        ])->assertCreated();

        $this->assertEquals(0.00, (float) Order::firstOrFail()->discount_amount);
    }

    public function test_short_cash_is_rejected_and_records_nothing(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 100,
        ])->assertStatus(422)->assertJsonPath('errors.amount_tendered.0', 'Cash tendered is less than the amount due.');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock, 'A refused sale must not move stock.');
    }

    public function test_a_sale_beyond_available_stock_is_refused(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 11]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 99999,
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_an_empty_basket_is_refused(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_prices_come_from_the_catalog_not_the_request(): void
    {
        // A tampered price in the payload must be ignored entirely.
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => 1,
                'unit_price' => 1,
            ]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 350,
        ])->assertCreated();

        $this->assertEquals(350.00, (float) Order::firstOrFail()->total_amount);
    }

    public function test_a_digital_sale_needs_no_tender_and_leaves_no_change(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Digital->value,
            'reference' => 'GC-12345',
        ])->assertCreated();

        $payment = Order::firstOrFail()->payment;

        $this->assertSame(PaymentMethod::Digital, $payment->method);
        $this->assertEquals(0.00, (float) $payment->change_due);
        $this->assertSame('GC-12345', $payment->reference);
    }

    public function test_duplicate_lines_for_the_same_variant_are_merged(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [
                ['product_variant_id' => $this->variant->id, 'quantity' => 1],
                ['product_variant_id' => $this->variant->id, 'quantity' => 2],
            ],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 2000,
        ])->assertCreated();

        $order = Order::with('items')->firstOrFail();

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame(7, $this->variant->fresh()->stock);
    }

    public function test_a_sale_can_be_attached_to_a_registered_customer(): void
    {
        $customer = Customer::factory()->create(['username' => 'jacobcamacho21']);

        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 350,
            'customer_id' => $customer->id,
            'customer_name' => 'jacobcamacho21',
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('jacobcamacho21', $order->buyerName());
    }

    public function test_a_walk_in_sale_has_no_customer_record(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 350,
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->assertNull($order->customer_id);
        $this->assertSame('Walk-in Customer', $order->buyerName());
    }

    public function test_receipt_numbers_are_sequential_and_unique(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
                'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
                'payment_method' => PaymentMethod::Cash->value,
                'amount_tendered' => 350,
            ])->assertCreated();
        }

        $numbers = Receipt::orderBy('id')->pluck('receipt_number')->all();

        $this->assertSame(['VD-000001', 'VD-000002', 'VD-000003'], $numbers);
    }

    public function test_an_inactive_account_cannot_use_the_register(): void
    {
        $disabled = User::factory()->staff()->inactive()->create();

        $this->actingAs($disabled)
            ->get(route('pos.register'))
            ->assertRedirect(route('staff.login'));
    }
}
