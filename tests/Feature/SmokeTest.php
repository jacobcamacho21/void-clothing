<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Walks every GET route in the application with realistic data in place, so a
 * page that only breaks when a relation is null or a collection is empty
 * cannot slip through.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_get_route_responds_without_a_server_error(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $product = Product::factory()->create(['name' => 'Alice', 'slug' => 'alice']);
        ProductVariant::factory()->for($product)->size('Medium')->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Approved,
        ]);
        OrderItem::factory()->for($order)->create();
        Payment::factory()->for($order)->create();

        $substitutions = [
            '{product:slug}' => $product->slug,
            '{product}' => $product->slug,
            '{order:order_ref}' => $order->order_ref,
            '{order}' => $order->order_ref,
        ];

        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (str_starts_with($uri, '_') || $uri === 'up' || str_contains($uri, '{')) {
                $uri = strtr($uri, $substitutions);
            }

            // Anything still carrying a placeholder needs a fixture this test
            // does not build; those paths are covered by their own tests.
            if (str_contains($uri, '{') || str_starts_with($uri, '_') || $uri === 'up') {
                continue;
            }

            $response = $this->actingAs($admin)->get('/'.ltrim($uri, '/'));

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                "GET /{$uri} returned {$response->getStatusCode()}"
            );

            $checked++;
        }

        $this->assertGreaterThan(15, $checked, 'The smoke test should be covering the whole surface.');
    }

    public function test_pages_render_with_an_entirely_empty_database(): void
    {
        // The very first run after install: no products, no orders, no
        // customers. Empty states must render rather than blow up.
        $admin = User::factory()->admin()->create();

        foreach ([
            '/',
            '/products',
            '/apparel',
            '/search',
            '/admin',
            '/admin/inventory',
            '/admin/orders',
            '/admin/customers',
            '/admin/users',
            '/pos',
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_the_dashboard_survives_orders_with_no_items(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/orders')->assertOk();
    }

    public function test_an_order_whose_product_was_deleted_still_renders(): void
    {
        $admin = User::factory()->admin()->create();

        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => 'Discontinued Design',
            'product_size' => 'Large',
        ]);

        // The catalog moves on; the order must still read correctly because
        // the line kept its own copy of the name, size and price.
        $variant->product->delete();

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Discontinued Design');

        $this->actingAs($admin)->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertSee('Discontinued Design');
    }

    public function test_a_guest_customer_can_fill_a_cart_and_keeps_it_after_signing_in(): void
    {
        $variant = ProductVariant::factory()->stock(5)->create();

        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $customer = Customer::factory()->create(['username' => 'latecomer']);

        $this->post(route('shop.login.attempt'), [
            'username' => 'latecomer',
            'password' => 'password',
        ]);

        $this->assertSame(2, $this->getJson(route('shop.cart.index'))->json('count'),
            'A basket built before signing in must survive the login.');
    }

    public function test_a_cashier_and_a_shopper_can_be_signed_in_at_once(): void
    {
        $cashier = User::factory()->staff()->create();
        $shopper = Customer::factory()->create();

        $this->actingAs($cashier)->actingAs($shopper, 'customer');

        $this->get(route('pos.register'))->assertOk();
        $this->get(route('shop.account'))->assertOk();
    }
}
