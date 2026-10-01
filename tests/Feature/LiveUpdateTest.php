<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_update_version_changes_when_catalog_data_changes(): void
    {
        $product = Product::factory()->create();

        $first = $this->getJson(route('updates.version'))
            ->assertOk()
            ->json('version');

        $product->forceFill([
            'name' => 'Updated design',
            'updated_at' => Carbon::create(2030, 1, 1),
        ])->save();
        Cache::forget('app.update-version');

        $second = $this->getJson(route('updates.version'))
            ->assertOk()
            ->json('version');

        $this->assertNotSame($first, $second);
    }
}
