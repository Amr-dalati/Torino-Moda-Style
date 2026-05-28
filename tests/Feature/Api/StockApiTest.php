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

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_stock_index_returns_rows(): void
    {
        $res = $this->getJson('/api/stock');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_stock_by_product_returns_rows(): void
    {
        $product = Product::query()->firstOrFail();

        $res = $this->getJson("/api/stock/product/{$product->id}");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.variant.product.id', $product->id);
    }

    public function test_stock_by_warehouse_returns_rows(): void
    {
        $warehouse = Warehouse::query()->firstOrFail();

        $res = $this->getJson("/api/stock/warehouse/{$warehouse->id}");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse.id', $warehouse->id);
    }
}

