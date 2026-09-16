<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The action panel on an order must only ever offer moves the signed-in user
 * can actually make.
 */
class OrderDetailPanelTest extends TestCase
{
    use RefreshDatabase;

    private function order(OrderStatus $status): Order
    {
        $order = Order::factory()->status($status)->create();
        OrderItem::factory()->for($order)->create();

        return $order;
    }

    public function test_staff_see_no_empty_action_panel_on_a_completed_order(): void
    {
        $staff = User::factory()->staff()->create();
        $order = $this->order(OrderStatus::Completed);

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('cannot move further')
            ->assertDontSee('Cancel order');
    }

    public function test_an_admin_can_cancel_a_completed_order_from_the_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(OrderStatus::Completed);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Cancel order');
    }

    public function test_a_pending_order_offers_approve_and_reject_to_staff(): void
    {
        $staff = User::factory()->staff()->create();
        $order = $this->order(OrderStatus::Pending);

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Approve payment')
            ->assertSee('Reject')
            ->assertDontSee('Cancel order');
    }

    public function test_a_terminal_order_offers_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(OrderStatus::Rejected);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Approve payment')
            ->assertDontSee('Move this order');
    }

    public function test_staff_can_view_an_order_proof_through_the_authenticated_endpoint(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $order = $this->order(OrderStatus::Pending);
        $order->update(['proof_of_payment' => 'payment_proofs/proof.jpg']);
        Storage::disk('public')->put('payment_proofs/proof.jpg', 'proof contents');

        $this->actingAs($staff)
            ->get(route('admin.orders.proof', $order))
            ->assertOk();
    }

    public function test_missing_order_proof_returns_not_found(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $order = $this->order(OrderStatus::Pending);
        $order->update(['proof_of_payment' => 'payment_proofs/missing.jpg']);

        $this->actingAs($staff)
            ->get(route('admin.orders.proof', $order))
            ->assertNotFound();
    }
}
