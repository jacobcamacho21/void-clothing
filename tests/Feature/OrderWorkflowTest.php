<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->staff()->create();

        $this->variant = ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Bubbles']))
            ->size('Large')
            ->price(300)
            ->stock(10)
            ->create();
    }

    private function pendingOrder(int $quantity = 2): Order
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'subtotal' => 300 * $quantity,
            'total_amount' => (300 * $quantity) + 50,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $this->variant->id,
            'product_id' => $this->variant->product_id,
            'product_name' => 'Bubbles',
            'product_size' => 'Large',
            'unit_price' => 300,
            'quantity' => $quantity,
            'line_total' => 300 * $quantity,
        ]);

        return $order->fresh('items');
    }

    public function test_approving_an_order_deducts_stock(): void
    {
        $order = $this->pendingOrder(2);

        $this->actingAs($this->staff)
            ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Approved->value])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Approved, $order->fresh()->status);
        $this->assertSame(8, $this->variant->fresh()->stock);
    }

    public function test_rejecting_an_approved_order_returns_stock(): void
    {
        $order = $this->pendingOrder(2);
        $service = app(OrderService::class);

        $service->transition($order, OrderStatus::Approved, $this->staff);
        $this->assertSame(8, $this->variant->fresh()->stock);

        $service->transition($order->fresh(), OrderStatus::Rejected, $this->staff);
        $this->assertSame(10, $this->variant->fresh()->stock, 'Rejecting must put the stock back.');
    }

    public function test_reverting_an_approved_order_to_pending_returns_stock(): void
    {
        $order = $this->pendingOrder(3);
        $service = app(OrderService::class);

        $service->transition($order, OrderStatus::Approved, $this->staff);
        $this->assertSame(7, $this->variant->fresh()->stock);

        $service->transition($order->fresh(), OrderStatus::Pending, $this->staff);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_rejecting_a_pending_order_leaves_stock_untouched(): void
    {
        $order = $this->pendingOrder(2);

        app(OrderService::class)->transition($order, OrderStatus::Rejected, $this->staff);

        $this->assertSame(10, $this->variant->fresh()->stock,
            'Pending orders never held stock, so rejecting one must not credit any back.');
    }

    public function test_completing_an_approved_order_keeps_stock_deducted_and_issues_a_receipt(): void
    {
        $order = $this->pendingOrder(2);
        $service = app(OrderService::class);

        $service->transition($order, OrderStatus::Approved, $this->staff);
        $completed = $service->transition($order->fresh(), OrderStatus::Completed, $this->staff);

        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertNotNull($completed->receipt);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        $order = $this->pendingOrder();

        $this->expectException(InvalidStatusTransitionException::class);

        // Pending cannot jump straight to Completed; it must be approved first.
        app(OrderService::class)->transition($order, OrderStatus::Completed, $this->staff);
    }

    public function test_a_rejected_order_is_terminal(): void
    {
        $order = $this->pendingOrder();
        app(OrderService::class)->transition($order, OrderStatus::Rejected, $this->staff);

        $this->expectException(InvalidStatusTransitionException::class);
        app(OrderService::class)->transition($order->fresh(), OrderStatus::Approved, $this->staff);
    }

    public function test_approving_beyond_available_stock_fails_and_leaves_the_status_alone(): void
    {
        $this->variant->update(['stock' => 1]);
        $order = $this->pendingOrder(5);

        $this->actingAs($this->staff)
            ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Approved->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(1, $this->variant->fresh()->stock);
    }

    public function test_every_status_change_is_recorded_with_its_author(): void
    {
        $order = $this->pendingOrder();

        app(OrderService::class)->transition($order, OrderStatus::Approved, $this->staff, 'Proof looks good.');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Approved->value,
            'changed_by' => $this->staff->id,
            'note' => 'Proof looks good.',
        ]);
    }

    public function test_only_an_admin_can_cancel_an_order(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->staff)
            ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Cancelled->value])
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Cancelled->value])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_only_an_admin_can_delete_an_order(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->staff)
            ->delete(route('admin.orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders'));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_deleting_an_approved_order_returns_its_stock(): void
    {
        $order = $this->pendingOrder(4);
        app(OrderService::class)->transition($order, OrderStatus::Approved, $this->admin);
        $this->assertSame(6, $this->variant->fresh()->stock);

        $this->actingAs($this->admin)->delete(route('admin.orders.destroy', $order));

        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_deleting_an_order_removes_its_items(): void
    {
        $order = $this->pendingOrder();
        $itemId = $order->items->first()->id;

        $this->actingAs($this->admin)->delete(route('admin.orders.destroy', $order));

        $this->assertDatabaseMissing('order_items', ['id' => $itemId]);
    }

    public function test_the_order_desk_lists_and_filters_orders(): void
    {
        $this->pendingOrder();
        Order::factory()->pos()->create(['order_ref' => 'ORDcounter0001']);

        $this->actingAs($this->staff)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('ORDcounter0001');

        $this->actingAs($this->staff)
            ->get(route('admin.orders', ['status' => OrderStatus::Pending->value]))
            ->assertOk()
            ->assertDontSee('ORDcounter0001');
    }

    public function test_the_order_detail_page_shows_items_and_history(): void
    {
        $order = $this->pendingOrder();
        app(OrderService::class)->transition($order, OrderStatus::Approved, $this->staff, 'Verified.');

        $this->actingAs($this->staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Bubbles')
            ->assertSee('Verified.')
            ->assertSee('Approve');
    }

    public function test_an_unknown_status_value_is_rejected_by_validation(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->staff)
            ->patch(route('admin.orders.status', $order), ['status' => 'shipped-to-mars'])
            ->assertSessionHasErrors('status');
    }
}
