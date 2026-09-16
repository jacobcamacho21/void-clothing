<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['username' => 'admin']);
        $this->staff = User::factory()->staff()->create(['username' => 'staff1']);
    }

    /* ------------------------------------------------------ authentication */

    public function test_staff_can_sign_in_and_land_on_the_register(): void
    {
        $this->post(route('staff.login.attempt'), [
            'username' => 'staff1',
            'password' => 'password',
        ])->assertRedirect(route('pos.register'));

        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_an_admin_lands_on_the_dashboard(): void
    {
        $this->post(route('staff.login.attempt'), [
            'username' => 'admin',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_bad_credentials_are_refused(): void
    {
        $this->post(route('staff.login.attempt'), [
            'username' => 'admin',
            'password' => 'nope',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->staff()->inactive()->create(['username' => 'retired']);

        $this->post(route('staff.login.attempt'), [
            'username' => 'retired',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('staff.login.attempt'), ['username' => 'admin', 'password' => 'nope']);
        }

        $this->post(route('staff.login.attempt'), ['username' => 'admin', 'password' => 'nope'])
            ->assertSessionHasErrorsIn('default', ['username']);

        $errors = session('errors')->get('username');
        $this->assertStringContainsString('Too many sign-in attempts', $errors[0]);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->admin)
            ->post(route('staff.logout'))
            ->assertRedirect(route('staff.login'));

        $this->assertGuest();
    }

    /* ------------------------------------------------------- authorization */

    public function test_staff_are_kept_out_of_the_admin_only_areas(): void
    {
        foreach ([route('admin.customers'), route('admin.users')] as $url) {
            $this->actingAs($this->staff)->get($url)->assertForbidden();
        }
    }

    public function test_an_admin_reaches_every_area(): void
    {
        foreach ([
            route('admin.dashboard'),
            route('admin.inventory'),
            route('admin.orders'),
            route('admin.customers'),
            route('admin.users'),
            route('pos.register'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_staff_reach_the_shared_areas(): void
    {
        foreach ([
            route('admin.dashboard'),
            route('admin.inventory'),
            route('admin.orders'),
            route('pos.register'),
        ] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }

    /* ----------------------------------------------------------- inventory */

    public function test_an_admin_can_add_a_variant_to_a_new_product(): void
    {
        $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Aurora',
            'category' => 'Premium',
            'size' => 'Large',
            'price' => 450,
            'stock' => 12,
        ])->assertRedirect(route('admin.inventory'));

        $this->assertDatabaseHas('products', ['name' => 'Aurora', 'slug' => 'aurora']);
        $this->assertDatabaseHas('product_variants', ['size' => 'Large', 'price' => 450, 'stock' => 12]);
    }

    public function test_adding_a_size_that_already_exists_is_refused(): void
    {
        $product = Product::factory()->create(['name' => 'Alice', 'slug' => 'alice']);
        ProductVariant::factory()->for($product)->size('Small')->create();

        $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'product_id' => $product->id,
            'size' => 'Small',
            'price' => 350,
            'stock' => 5,
        ])->assertSessionHasErrors('size');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_adding_a_variant_without_a_product_or_a_name_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'size' => 'Small',
            'price' => 350,
            'stock' => 5,
        ])->assertSessionHasErrors('name');
    }

    public function test_staff_can_correct_stock_but_not_price(): void
    {
        $variant = ProductVariant::factory()->price(350)->stock(4)->create();

        $this->actingAs($this->staff)->patch(route('admin.inventory.update', $variant), [
            'stock' => 20,
            'price' => 1,
        ])->assertRedirect(route('admin.inventory'));

        $variant->refresh();

        $this->assertSame(20, $variant->stock, 'Staff must be able to correct a count.');
        $this->assertEquals(350.00, (float) $variant->price, 'Staff must not be able to reprice stock.');
    }

    public function test_an_admin_can_change_price_and_stock(): void
    {
        $variant = ProductVariant::factory()->price(350)->stock(4)->size('Medium')->create();

        $this->actingAs($this->admin)->patch(route('admin.inventory.update', $variant), [
            'stock' => 30,
            'price' => 425,
            'size' => 'Medium',
        ])->assertRedirect();

        $variant->refresh();

        $this->assertSame(30, $variant->stock);
        $this->assertEquals(425.00, (float) $variant->price);
    }

    public function test_negative_stock_is_refused(): void
    {
        $variant = ProductVariant::factory()->stock(4)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.inventory.update', $variant), ['stock' => -5])
            ->assertSessionHasErrors('stock');

        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_staff_cannot_delete_a_variant(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->staff)
            ->delete(route('admin.inventory.destroy', $variant))
            ->assertForbidden();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_removing_the_last_variant_retires_the_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAs($this->admin)->delete(route('admin.inventory.destroy', $variant));

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_inventory_search_narrows_the_list(): void
    {
        ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Philemon']))
            ->create(['sku' => 'SKU-KEEP']);

        ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Evoker']))
            ->create(['sku' => 'SKU-DROP']);

        // Asserted on the SKUs rather than the product names: every product
        // name also appears in the "add variant" dropdown on this page.
        $this->actingAs($this->admin)
            ->get(route('admin.inventory', ['q' => 'Philemon']))
            ->assertOk()
            ->assertSee('SKU-KEEP')
            ->assertDontSee('SKU-DROP');
    }

    /* --------------------------------------------------------- staff users */

    public function test_an_admin_can_create_a_staff_account(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'username' => 'cashier2',
            'password' => 'a-good-password',
            'role' => 'staff',
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['username' => 'cashier2', 'role' => 'staff']);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'username' => 'weak',
            'password' => 'short',
            'role' => 'staff',
        ])->assertSessionHasErrors('password');
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.update', $this->admin), [
            'username' => $this->admin->username,
            'role' => 'staff',
            'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_the_last_administrator_cannot_be_deleted(): void
    {
        $other = User::factory()->admin()->create();

        // Deleting one of two is fine.
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.users'));

        // Now only one admin remains, and it is the signed-in account.
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_a_deactivated_account_is_signed_out_on_its_next_request(): void
    {
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk();

        $this->staff->update(['is_active' => false]);

        $this->actingAs($this->staff)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('staff.login'));
    }

    /* ----------------------------------------------------------- customers */

    public function test_an_admin_can_create_and_edit_a_customer(): void
    {
        $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'username' => 'walkin01',
            'email' => 'walkin01@example.com',
            'password' => 'a-good-password',
            'street' => 'Tangulan Street',
            'city' => 'Kawit',
        ])->assertRedirect(route('admin.customers'));

        $customer = Customer::firstWhere('username', 'walkin01');
        $this->assertNotNull($customer);
        $this->assertSame(1, $customer->addresses()->count());

        $this->actingAs($this->admin)->patch(route('admin.customers.update', $customer), [
            'username' => 'walkin-renamed',
            'email' => 'walkin01@example.com',
        ])->assertRedirect();

        $this->assertSame('walkin-renamed', $customer->fresh()->username);
    }

    public function test_deleting_a_customer_keeps_their_orders(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)->delete(route('admin.customers.destroy', $customer));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertNull($order->fresh()->customer_id);
    }

    /* ----------------------------------------------------------- dashboard */

    public function test_the_dashboard_renders_for_both_roles(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Revenue');

        $this->actingAs($this->staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Total Revenue');
    }

    public function test_the_dashboard_accepts_only_the_offered_ranges(): void
    {
        foreach ([7, 30, 90] as $range) {
            $this->actingAs($this->admin)
                ->get(route('admin.dashboard', ['range' => $range]))
                ->assertOk();
        }

        // An unexpected range falls back to the default rather than erroring.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['range' => 9999]))
            ->assertOk()
            ->assertSee('last 30 days');
    }
}
