<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_products_endpoints_require_auth(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
        $this->getJson('/api/products/search?q=Classic')->assertStatus(401);
        $this->getJson('/api/products/barcode/6281001001018')->assertStatus(401);
    }

    public function test_products_search_returns_results(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/products/search?q=Classic');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_code', 'TMS-SHOE-001');
    }

    public function test_products_barcode_returns_variant_match(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/products/barcode/6281001001018');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.product_code', 'TMS-SHOE-001')
            ->assertJsonPath('data.variant.barcode', '6281001001018');
    }

    public function test_products_barcode_falls_back_to_product_barcode(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/products/barcode/6281001001001');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.product_code', 'TMS-SHOE-001')
            ->assertJsonPath('data.variant', null);
    }

    public function test_products_barcode_invalid_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $invalid = str_repeat('1', 101);
        $this->getJson("/api/products/barcode/{$invalid}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_product_detail_happy_path(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.product_code', 'TMS-SHOE-001');
    }

    public function test_product_detail_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_phoenix_sync_is_idempotent(): void
    {
        // first run happened in setUp
        $this->artisan('phoenix:sync');

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, \App\Models\ProductVariant::query()->count());
        $this->assertSame(1, \App\Models\StockLevel::query()->count());
        $this->assertSame(1, \App\Models\Warehouse::query()->count());
    }
}

