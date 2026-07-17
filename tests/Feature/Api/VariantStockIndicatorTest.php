<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VariantStockIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_product_detail_exposes_is_in_stock_for_variants(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $response = $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $variants = collect($response->json('data.variants'));
        $match = $variants->firstWhere('id', $variant->id);

        $this->assertNotNull($match);
        $this->assertTrue($match['is_in_stock']);
        $this->assertArrayNotHasKey('available_quantity', $match);
        $this->assertArrayNotHasKey('quantity_reserved', $match);
        $this->assertArrayNotHasKey('warehouse_id', $match);
    }

    public function test_product_detail_marks_variant_out_of_stock_when_unavailable(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 2,
            'quantity_reserved' => 2,
        ]);

        $response = $this->getJson("/api/products/{$product->id}")
            ->assertOk();

        $match = collect($response->json('data.variants'))->firstWhere('id', $variant->id);

        $this->assertNotNull($match);
        $this->assertFalse($match['is_in_stock']);
    }

    public function test_stock_indicator_ignores_inactive_warehouses(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $inactiveWarehouse = Warehouse::query()->create([
            'code' => 'INACTIVE-WH',
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        StockLevel::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $inactiveWarehouse->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $response = $this->getJson("/api/products/{$product->id}")
            ->assertOk();

        $match = collect($response->json('data.variants'))->firstWhere('id', $variant->id);

        $this->assertNotNull($match);
        $this->assertFalse($match['is_in_stock']);
    }
}
