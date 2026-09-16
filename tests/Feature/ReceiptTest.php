<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->staff()->create(['name' => 'Counter Staff']);

        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Ghost Mode']))
            ->size('Large')
            ->price(350)
            ->stock(20)
            ->create();
    }

    private function completedSale(): Order
    {
        $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => 1000,
        ])->assertCreated();

        return Order::with(['items', 'payment', 'receipt'])->firstOrFail();
    }

    public function test_a_completed_sale_issues_exactly_one_receipt(): void
    {
        $order = $this->completedSale();

        $this->assertNotNull($order->receipt);
        $this->assertSame(1, Receipt::count());
        $this->assertMatchesRegularExpression('/^VD-\d{6}$/', $order->receipt->receipt_number);
    }

    public function test_the_receipt_shows_every_figure_the_customer_needs(): void
    {
        $order = $this->completedSale();

        $response = $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order))
            ->assertOk();

        $response->assertSee($order->receipt->receipt_number);   // Receipt number
        $response->assertSee($order->order_ref);                 // Order number
        $response->assertSee('Ghost Mode');                      // Product
        $response->assertSee('350.00 each', false);              // Unit price and quantity
        $response->assertSee('700.00');                          // Subtotal and total
        $response->assertSee('Cash');                            // Payment method
        $response->assertSee('1,000.00');                        // Tendered
        $response->assertSee('300.00');                          // Change
        $response->assertSee('Counter Staff');                   // Cashier
        $response->assertSee('Counter 01');                      // Terminal
        $response->assertSee('Walk-in Customer');                // Customer
        $response->assertSee('PAID');
    }

    public function test_the_receipt_does_not_itemise_tax(): void
    {
        // The shop does not break VAT out to the customer. The figure is still
        // computed and stored for reporting; it just never reaches the paper.
        $order = $this->completedSale();

        $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertDontSee('VAT')
            ->assertDontSee('Tax');

        $this->assertGreaterThan(0, (float) $order->tax_amount,
            'The tax is still recorded on the order for reporting.');
    }

    public function test_the_receipt_carries_the_order_reference_as_a_barcode(): void
    {
        $order = $this->completedSale();

        $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertSee('rcpt-barcode');
    }

    public function test_the_register_can_fetch_the_receipt_on_its_own(): void
    {
        // The register drops the rendered document straight into its modal,
        // so the copy on screen is the copy that prints.
        $order = $this->completedSale();

        $response = $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order).'?fragment=1')
            ->assertOk();

        $response->assertSee('rcpt-meta');
        $response->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_the_receipt_shows_the_shipping_line_for_an_online_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Approved,
            'subtotal' => 350,
            'shipping_fee' => 50,
            'total_amount' => 400,
            'shipping_address' => 'Tangulan Street, Kawit, Cavite 4102, Philippines',
        ]);

        OrderItem::factory()->for($order)->create(['product_name' => 'Time', 'product_size' => 'Large']);
        Payment::factory()->for($order)->create(['method' => PaymentMethod::Digital->value]);

        $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertSee('Shipping')
            ->assertSee('50.00')
            ->assertSee('Tangulan Street')
            ->assertSee('APPROVED');
    }

    public function test_printing_counts_the_print_and_marks_later_copies_as_reprints(): void
    {
        $order = $this->completedSale();

        $this->actingAs($this->cashier)->get(route('pos.receipt.print', $order))->assertOk();
        $this->assertSame(1, $order->receipt->fresh()->print_count);

        $this->actingAs($this->cashier)
            ->get(route('pos.receipt.print', $order))
            ->assertOk()
            ->assertSee('copy 2');

        $this->assertSame(2, $order->receipt->fresh()->print_count);
    }

    public function test_a_reprint_keeps_the_original_receipt_number(): void
    {
        $order = $this->completedSale();
        $original = $order->receipt->receipt_number;

        $this->actingAs($this->cashier)->get(route('pos.receipt.print', $order));
        $this->actingAs($this->cashier)->get(route('pos.receipt.print', $order));

        $this->assertSame($original, $order->receipt->fresh()->receipt_number);
        $this->assertSame(1, Receipt::count());
    }

    public function test_an_order_without_a_receipt_gets_one_on_first_view(): void
    {
        // Mirrors an order imported from the legacy system, which had no
        // receipt table at all.
        $order = Order::factory()->create(['status' => OrderStatus::Approved]);
        OrderItem::factory()->for($order)->create();

        $this->assertNull($order->receipt);

        $this->actingAs($this->cashier)->get(route('pos.receipt', $order))->assertOk();

        $this->assertNotNull($order->fresh()->receipt);
    }

    public function test_a_cancelled_order_prints_a_void_stamp(): void
    {
        $order = Order::factory()->status(OrderStatus::Cancelled)->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertSee('CANCELLED');
    }

    public function test_receipts_are_not_public(): void
    {
        $order = Order::factory()->create();

        $this->get(route('pos.receipt', $order))->assertRedirect(route('staff.login'));
    }

    public function test_an_unknown_order_reference_is_a_404(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('pos.receipt', ['order' => 'ORDdoesnotexist']))
            ->assertNotFound();
    }

    public function test_receipt_numbering_survives_a_gap_in_the_sequence(): void
    {
        $service = app(ReceiptService::class);

        $first = Order::factory()->create();
        $service->issue($first, $this->cashier);

        $second = Order::factory()->create();
        $service->issue($second, $this->cashier);

        // The middle receipt is removed, as a deleted order would remove it.
        $second->receipt->delete();

        $third = Order::factory()->create();
        $number = $service->issue($third, $this->cashier)->receipt_number;

        $this->assertSame('VD-000002', $number,
            'Numbering continues from the highest surviving receipt.');
    }
}
