<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_stock_endpoints_require_auth(): void
    {
        $this->getJson('/api/stock')->assertStatus(401);

        // auth middleware runs before route model not found
        $this->getJson('/api/stock/product/1')->assertStatus(401);
        $this->getJson('/api/stock/warehouse/1')->assertStatus(401);
    }

    public function test_stock_index_returns_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/stock');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_stock_by_product_returns_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();

        $res = $this->getJson("/api/stock/product/{$product->id}");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.variant.product.id', $product->id);
    }

    public function test_stock_by_warehouse_returns_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $warehouse = Warehouse::query()->firstOrFail();

        $res = $this->getJson("/api/stock/warehouse/{$warehouse->id}");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse.id', $warehouse->id);
    }

    public function test_stock_by_product_404_for_missing_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/stock/product/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_stock_by_warehouse_404_for_missing_warehouse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/stock/warehouse/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}

