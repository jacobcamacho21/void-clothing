<?php

namespace Tests\Feature;

use App\Models\HeldSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeldSaleTest extends TestCase
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
            ->size('Small')
            ->price(350)
            ->stock(10)
            ->create();
    }

    public function test_a_basket_can_be_held_and_listed(): void
    {
        // The hold's reference is issued by the server, in the house format,
        // rather than being whatever the browser happened to send.
        $created = $this->actingAs($this->cashier)->postJson(route('pos.held.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'customer_name' => 'Walk-in Customer',
        ])->assertCreated()->assertJsonPath('held_count', 1);

        $reference = $created->json('held.reference');
        $this->assertMatchesRegularExpression('/^HOLD-\d{6}-\d{4}$/', $reference);

        $listing = $this->actingAs($this->cashier)
            ->getJson(route('pos.held.index'))
            ->assertOk()
            ->assertJsonPath('held.0.reference', $reference)
            ->assertJsonPath('held.0.item_count', 2);

        $this->assertEqualsWithDelta(700.0, $listing->json('held.0.total'), 0.001);
    }

    public function test_each_hold_gets_its_own_reference(): void
    {
        $references = collect(range(1, 3))->map(fn () => $this->actingAs($this->cashier)
            ->postJson(route('pos.held.store'), [
                'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            ])->assertCreated()->json('held.reference'));

        $this->assertCount(3, $references->unique(),
            'Two parked baskets must never answer to the same reference.');
    }

    public function test_holding_a_sale_does_not_move_stock(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.held.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(10, $this->variant->fresh()->stock,
            'A held sale is not a sale; the stock stays available to everyone else.');
    }

    public function test_resuming_removes_the_hold_and_returns_the_basket(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.held.store'), [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
        ]);

        $held = HeldSale::firstOrFail();

        $this->actingAs($this->cashier)
            ->deleteJson(route('pos.held.destroy', $held))
            ->assertOk()
            ->assertJsonPath('resumed.reference', $held->reference)
            ->assertJsonPath('held_count', 0);

        $this->assertDatabaseMissing('held_sales', ['id' => $held->id]);
    }

    public function test_a_cashier_cannot_resume_another_cashiers_hold(): void
    {
        $other = User::factory()->staff()->create();

        $held = HeldSale::create([
            'reference' => 'ORDheld00000004',
            'cashier_id' => $other->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'item_count' => 1,
            'total' => 350,
        ]);

        $this->actingAs($this->cashier)
            ->deleteJson(route('pos.held.destroy', $held))
            ->assertForbidden();

        $this->assertDatabaseHas('held_sales', ['id' => $held->id]);
    }

    public function test_a_cashier_only_sees_their_own_holds(): void
    {
        $other = User::factory()->staff()->create();

        HeldSale::create([
            'reference' => 'ORDother0000001',
            'cashier_id' => $other->id,
            'items' => [],
            'item_count' => 0,
            'total' => 0,
        ]);

        $this->actingAs($this->cashier)
            ->getJson(route('pos.held.index'))
            ->assertOk()
            ->assertJsonCount(0, 'held');
    }

    public function test_holding_an_empty_basket_is_refused(): void
    {
        $this->actingAs($this->cashier)->postJson(route('pos.held.store'), [
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_holds_require_a_signed_in_cashier(): void
    {
        $this->getJson(route('pos.held.index'))->assertUnauthorized();
    }
}
