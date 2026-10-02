<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'name' => 'Temperance',
            'slug' => 'temperance',
        ]);

        $this->variant = ProductVariant::factory()
            ->for($this->product)
            ->size('Medium')
            ->price(350)
            ->stock(5)
            ->create();
    }

    /* ------------------------------------------------------------ browsing */

    public function test_the_storefront_pages_render(): void
    {
        $this->get(route('shop.home'))->assertOk()->assertSee('Temperance');
        $this->get(route('shop.products'))->assertOk()->assertSee('Temperance');
        $this->get(route('shop.apparel'))->assertOk();
        $this->get(route('shop.product', $this->product))->assertOk()->assertSee('Size Chart');
    }

    public function test_a_product_page_discovers_numbered_gallery_images(): void
    {
        $this->product->update(['image' => 'Alice.png']);

        $this->get(route('shop.product', $this->product))
            ->assertOk()
            ->assertSee('images/products/Alice.png')
            ->assertSee('images/products/Alice-2.png')
            ->assertSee('Next Temperance image');
    }

    public function test_an_inactive_product_is_hidden(): void
    {
        $this->product->update(['is_active' => false]);

        $this->get(route('shop.products'))->assertOk()->assertDontSee('Temperance');
        $this->get(route('shop.product', $this->product))->assertNotFound();
    }

    public function test_search_matches_on_name(): void
    {
        Product::factory()->create(['name' => 'Evoker', 'slug' => 'evoker']);

        $this->get(route('shop.search', ['q' => 'temper']))
            ->assertOk()
            ->assertSee('Temperance')
            ->assertDontSee('Evoker');
    }

    /* ---------------------------------------------------------------- cart */

    public function test_items_can_be_added_updated_and_removed(): void
    {
        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
        ])->assertOk()->assertJsonPath('count', 2);

        $this->patchJson(route('shop.cart.update'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ])->assertOk()->assertJsonPath('count', 3);

        $this->deleteJson(route('shop.cart.remove'), [
            'product_variant_id' => $this->variant->id,
        ])->assertOk()->assertJsonPath('count', 0);
    }

    public function test_the_cart_will_not_exceed_available_stock(): void
    {
        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 6,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, $this->getJson(route('shop.cart.index'))->json('count'));
    }

    public function test_cart_totals_are_priced_from_the_catalog_and_include_shipping(): void
    {
        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);

        $totals = $this->getJson(route('shop.cart.index'))->json('totals');

        $this->assertEquals(700.0, $totals['subtotal']);
        $this->assertEquals(config('void.shipping_fee'), $totals['shipping_fee']);
        $this->assertEquals(700.0 + config('void.shipping_fee'), $totals['total_amount']);
    }

    public function test_the_online_channel_gets_no_bundle_discount(): void
    {
        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 4,
        ]);

        $this->assertEquals(0.0, $this->getJson(route('shop.cart.index'))->json('totals.discount_amount'));
    }

    public function test_a_cart_line_is_trimmed_when_stock_drops_beneath_it(): void
    {
        $this->postJson(route('shop.cart.add'), [
            'product_variant_id' => $this->variant->id,
            'quantity' => 5,
        ])->assertOk();

        // Someone else buys four of them at the counter.
        $this->variant->update(['stock' => 1]);

        $this->assertSame(1, $this->getJson(route('shop.cart.index'))->json('count'));
    }

    /* ------------------------------------------------------------- account */

    public function test_registration_signs_the_shopper_in(): void
    {
        $this->post(route('shop.register.attempt'), [
            'username' => 'newshopper',
            'email' => 'new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms_accepted' => '1',
        ])->assertRedirect(route('shop.home'));

        $this->assertAuthenticatedAs(Customer::firstWhere('username', 'newshopper'), 'customer');
    }

    public function test_registration_rejects_a_duplicate_username(): void
    {
        Customer::factory()->create(['username' => 'taken']);

        $this->post(route('shop.register.attempt'), [
            'username' => 'taken',
            'email' => 'other@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms_accepted' => '1',
        ])->assertSessionHasErrors('username');
    }

    public function test_login_and_logout(): void
    {
        $customer = Customer::factory()->create(['username' => 'shopper']);

        $this->post(route('shop.login.attempt'), [
            'username' => 'shopper',
            'password' => 'password',
        ])->assertRedirect(route('shop.home'));

        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post(route('shop.logout'))->assertRedirect(route('shop.home'));
        $this->assertGuest('customer');
    }

    public function test_bad_credentials_are_refused(): void
    {
        Customer::factory()->create(['username' => 'shopper']);

        $this->post(route('shop.login.attempt'), [
            'username' => 'shopper',
            'password' => 'wrong',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('customer');
    }

    public function test_the_account_page_needs_a_signed_in_shopper(): void
    {
        $this->get(route('shop.account'))->assertRedirect(route('shop.login'));
    }

    public function test_the_account_page_links_to_customer_support(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account'))
            ->assertOk()
            ->assertSee('Contact support')
            ->assertSee(route('shop.contact'));
    }

    public function test_a_customer_can_review_a_product_from_a_completed_order_once(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account'))
            ->assertOk()
            ->assertSee('Share your experience')
            ->assertSee($this->product->name);

        $this->actingAs($customer, 'customer')
            ->from(route('shop.account'))
            ->post(route('shop.reviews.store'), [
                'product_id' => $this->product->id,
                'rating' => 5,
                'body' => 'The fit and print are excellent. I love this piece.',
            ])
            ->assertRedirect(route('shop.account'));

        $this->assertDatabaseHas('product_reviews', [
            'customer_id' => $customer->id,
            'product_id' => $this->product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'is_approved' => true,
            'verified_purchase' => true,
        ]);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('The fit and print are excellent. I love this piece.');

        $this->get(route('shop.product', $this->product))
            ->assertOk()
            ->assertSee('Temperance reviews')
            ->assertSee('The fit and print are excellent. I love this piece.');
    }

    /* ------------------------------------------------------------ checkout */

    public function test_checkout_requires_a_signed_in_shopper(): void
    {
        $this->get(route('shop.checkout'))->assertRedirect(route('shop.login'));
    }

    public function test_checkout_with_an_empty_cart_sends_the_shopper_back_to_the_catalog(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('shop.checkout'))
            ->assertRedirect(route('shop.products'));
    }

    public function test_placing_an_order_records_it_as_pending_without_moving_stock(): void
    {
        $this->fakeCheckoutProviders();

        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->postJson(route('shop.cart.add'), [
                'product_variant_id' => $this->variant->id,
                'quantity' => 2,
            ]);

        $this->actingAs($customer, 'customer')->post(route('shop.checkout.store'), [
            'recipient_name' => 'Jacob Camacho',
            'phone' => '09766538131',
            'street' => 'Tangulan Street Kaingen',
            'city' => 'Kawit',
            'province' => 'Cavite',
            'postal_code' => '4102',
            'country' => 'Philippines',
            'payment' => 'paymongo',
            'agreed_to_terms' => '1',
        ])->assertRedirect('https://checkout.paymongo.test/cs_test_123');

        $order = Order::firstOrFail();

        $this->assertSame(OrderChannel::Online, $order->channel);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertEquals(700.00, (float) $order->subtotal);
        $this->assertEquals(145.50, (float) $order->shipping_fee);
        $this->assertEquals(845.50, (float) $order->total_amount);
        $this->assertSame('pending', $order->payment->status);
        $this->assertSame('cs_test_123', $order->payment->provider_checkout_id);

        $this->assertSame(5, $this->variant->fresh()->stock,
            'Online orders must not move stock until a staff member approves them.');

        // The cart is emptied once the order is on record.
        $this->assertSame(0, $this->getJson(route('shop.cart.index'))->json('count'));
    }

    public function test_checkout_without_a_payment_method_is_refused(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->postJson(route('shop.cart.add'), ['product_variant_id' => $this->variant->id, 'quantity' => 1]);

        $this->actingAs($customer, 'customer')->post(route('shop.checkout.store'), [
            'recipient_name' => 'Jacob Camacho',
            'phone' => '09766538131',
            'street' => 'Tangulan Street',
            'city' => 'Kawit',
            'province' => 'Cavite',
            'postal_code' => '4102',
            'country' => 'Philippines',
            'agreed_to_terms' => '1',
        ])->assertSessionHasErrors('payment');

        $this->assertSame(0, Order::count());
    }

    public function test_the_checkout_address_is_saved_for_next_time(): void
    {
        $this->fakeCheckoutProviders();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->postJson(route('shop.cart.add'), ['product_variant_id' => $this->variant->id, 'quantity' => 1]);

        $this->actingAs($customer, 'customer')->post(route('shop.checkout.store'), [
            'recipient_name' => 'Jacob Camacho',
            'phone' => '09766538131',
            'street' => 'Tangulan Street Kaingen',
            'city' => 'Kawit',
            'province' => 'Cavite',
            'postal_code' => '4102',
            'country' => 'Philippines',
            'payment' => 'paymongo',
            'agreed_to_terms' => '1',
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'street' => 'Tangulan Street Kaingen',
        ]);
    }

    private function fakeCheckoutProviders(): void
    {
        config()->set('services.lalamove.pickup_address', 'Tangulan Street, Kawit, Cavite');
        config()->set('services.lalamove.pickup_latitude', '14.4450');
        config()->set('services.lalamove.pickup_longitude', '120.9010');

        Http::fake([
            '*lalamove.com/v3/quotations' => Http::response([
                'data' => [
                    'quotationId' => 'quote-checkout-123',
                    'expiresAt' => '2026-10-02T12:05:00.000Z',
                    'priceBreakdown' => ['total' => '145.50', 'currency' => 'PHP'],
                    'distance' => ['value' => '8200'],
                ],
            ], 201),
            '*paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_123'],
                ],
            ], 201),
        ]);
    }

    /* -------------------------------------------------------- order status */

    public function test_a_shopper_sees_their_own_order_and_its_status(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Approved,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account.order', $order))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee($order->order_ref)
            ->assertSee('Contact support')
            ->assertSee(route('shop.contact'));
    }

    public function test_a_shopper_cannot_open_someone_elses_order(): void
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('shop.account.order', $order))
            ->assertNotFound();
    }

    public function test_addresses_can_be_managed_and_are_scoped_to_their_owner(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post(route('shop.account.addresses.store'), [
            'recipient_name' => 'Jacob Camacho',
            'phone' => '09766538131',
            'street' => 'Tangulan Street',
            'city' => 'Kawit',
            'province' => 'Cavite',
            'postal_code' => '4102',
            'country' => 'Philippines',
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', ['customer_id' => $customer->id]);

        $foreign = CustomerAddress::factory()->create();

        $this->actingAs($customer, 'customer')
            ->delete(route('shop.account.addresses.destroy', $foreign))
            ->assertNotFound();

        $this->assertDatabaseHas('customer_addresses', ['id' => $foreign->id]);
    }
}
