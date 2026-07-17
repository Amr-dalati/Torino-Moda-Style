<?php

namespace Tests\Unit\Support;

use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Support\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_product_variant_save_bumps_catalog_cache_version(): void
    {
        $before = (int) Cache::get('catalog:version', 1);
        $variant = ProductVariant::query()->firstOrFail();
        $variant->update(['is_active' => ! $variant->is_active]);
        $after = (int) Cache::get('catalog:version', 1);

        $this->assertGreaterThan($before, $after);
    }

    public function test_stock_level_save_bumps_catalog_cache_version(): void
    {
        $variant = ProductVariant::query()->firstOrFail();
        $warehouse = Warehouse::query()->firstOrFail();
        $before = (int) Cache::get('catalog:version', 1);

        StockLevel::query()->updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
            ],
            ['quantity_on_hand' => 99],
        );

        $after = (int) Cache::get('catalog:version', 1);

        $this->assertGreaterThan($before, $after);
    }
}
