<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Order references.
 *
 * The shape is `VD-POS-260824-0007`: the store's mark, the channel, the
 * trading date, and a counter that restarts each day. What matters is that it
 * is readable, unique, and issued by the server — never by the browser.
 */
class OrderReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->staff()->create();

        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Alice']))
            ->size('Large')
            ->price(350)
            ->stock(50)
            ->create();
    }

    public function test_a_counter_sale_is_referenced_by_channel_and_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 14:00:00'));

        $order = $this->ringUp();

        $this->assertSame('VD-POS-260824-0001', $order->order_ref);
    }

    public function test_an_online_order_carries_the_web_channel(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 14:00:00'));

        $reference = app(ReferenceService::class)->orderReference(OrderChannel::Online);

        $this->assertSame('VD-WEB-260824-0001', $reference);
    }

    public function test_the_counter_runs_on_through_the_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));

        $first = $this->ringUp();

        Carbon::setTestNow(Carbon::parse('2026-08-24 17:30:00'));

        $second = $this->ringUp();

        $this->assertSame('VD-POS-260824-0001', $first->order_ref);
        $this->assertSame('VD-POS-260824-0002', $second->order_ref);
    }

    public function test_the_counter_restarts_the_next_trading_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 17:00:00'));
        $this->ringUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00'));
        $tomorrow = $this->ringUp();

        $this->assertSame('VD-POS-260825-0001', $tomorrow->order_ref);
    }

    public function test_the_two_channels_count_separately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00'));

        $references = app(ReferenceService::class);

        Order::factory()->create(['order_ref' => $references->orderReference(OrderChannel::Online)]);
        $counter = $this->ringUp();

        $this->assertSame('VD-POS-260824-0001', $counter->order_ref,
            'An online order must not push the counter sequence along.');
    }

    public function test_a_reference_already_in_use_is_stepped_over(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00'));

        // A reference imported from elsewhere already occupies today's 0001.
        Order::factory()->create(['order_ref' => 'VD-POS-260824-0001']);

        $this->assertSame('VD-POS-260824-0002', $this->ringUp()->order_ref);
    }

    public function test_references_are_unique_across_a_busy_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00'));

        $references = collect(range(1, 12))->map(fn () => $this->ringUp()->order_ref);

        $this->assertCount(12, $references->unique());
    }

    public function test_the_browser_cannot_choose_its_own_reference(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 1000,
            'order_ref' => 'TOTALLY-MADE-UP',
        ])->assertCreated();

        $this->assertNotSame('TOTALLY-MADE-UP', Order::firstOrFail()->order_ref);
        $this->assertMatchesRegularExpression('/^VD-POS-\d{6}-\d{4}$/', Order::firstOrFail()->order_ref);
    }

    public function test_a_reference_fits_the_column_it_is_stored_in(): void
    {
        $reference = app(ReferenceService::class)->orderReference(OrderChannel::Online);

        $this->assertLessThanOrEqual(32, strlen($reference));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ringUp(?Customer $customer = null): Order
    {
        return app(OrderService::class)->createPosSale(
            lines: [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            cashier: $this->cashier,
            method: PaymentMethod::Cash,
            tendered: 1000,
            customer: $customer,
        );
    }
}
