<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_a_lalamove_booking_for_a_paid_order(): void
    {
        $staff = User::factory()->staff()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Approved]);
        Delivery::create([
            'order_id' => $order->id,
            'provider' => 'lalamove',
            'quoted_fee' => 145.50,
            'status' => 'awaiting_booking',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.orders.delivery', $order), [
                'actual_fee' => '150.00',
                'booking_reference' => 'LAM-123456',
                'tracking_url' => 'https://share.lalamove.com/LAM-123456',
                'notes' => 'Booked through the Lalamove app.',
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'actual_fee' => 150.00,
            'booking_reference' => 'LAM-123456',
            'status' => 'booked',
        ]);
    }
}
