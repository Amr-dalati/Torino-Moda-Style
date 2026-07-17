<?php

namespace Tests\Feature\Api;

use App\Enums\ProductSource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_categories_endpoint_returns_expected_structure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'code', 'name_ar', 'name_en', 'image_url', 'is_active'],
                ],
            ]);
    }

    public function test_brands_endpoint_returns_expected_structure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'code', 'name', 'name_ar', 'name_en', 'logo_url', 'is_active'],
                ],
            ]);
    }

    public function test_brand_name_falls_back_for_manual_brand_without_name(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $brand = Brand::query()->create([
            'code' => 'MANUAL-BRAND',
            'name' => null,
            'name_en' => 'Manual Brand EN',
            'name_ar' => null,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonPath('success', true);

        $match = collect($response->json('data'))
            ->firstWhere('code', 'MANUAL-BRAND');

        $this->assertNotNull($match);
        $this->assertSame('Manual Brand EN', $match['name']);
        $this->assertIsString($match['name']);
    }

    public function test_products_support_category_filter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $category = Category::query()->where('code', 'SHOES')->firstOrFail();

        $this->getJson("/api/products?category_id={$category->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.product_code', 'TMS-SHOE-001');
    }

    public function test_products_support_brand_filter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $brand = Brand::query()->where('code', 'TORINO')->firstOrFail();

        $this->getJson("/api/products?brand_id={$brand->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.product_code', 'TMS-SHOE-001');
    }

    public function test_products_support_combined_filters_and_sort(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $category = Category::query()->where('code', 'SHOES')->firstOrFail();
        $brand = Brand::query()->where('code', 'TORINO')->firstOrFail();

        $this->getJson("/api/products?category_id={$category->id}&brand_id={$brand->id}&sort=price_asc")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.product_code', 'TMS-SHOE-001');
    }

    public function test_product_detail_includes_image_fields_when_no_images(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.primary_image_url', null)
            ->assertJsonPath('data.images', []);
    }

    public function test_product_detail_includes_primary_image_and_images_array(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->firstOrFail();
        Storage::disk('public')->put("products/{$product->id}/test.jpg", 'fake-image');

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => "products/{$product->id}/test.jpg",
            'disk' => 'public',
            'alt_text' => 'Front view',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.images.0.alt_text', 'Front view')
            ->assertJsonPath('data.images.0.is_primary', true);

        $this->assertNotNull($response->json('data.primary_image_url'));
        $this->assertStringContainsString("products/{$product->id}/test.jpg", $response->json('data.primary_image_url'));
    }

    public function test_manual_product_can_be_created_and_is_not_overwritten_by_phoenix_sync(): void
    {
        $category = Category::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();

        $manual = Product::query()->create([
            'product_code' => 'MANUAL-001',
            'name_en' => 'Manual Product',
            'name_ar' => 'منتج يدوي',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sale_price' => 50.00,
            'is_active' => true,
            'is_visible' => true,
            'source' => ProductSource::Manual,
        ]);

        $this->artisan('phoenix:sync');

        $manual->refresh();

        $this->assertSame('MANUAL-001', $manual->product_code);
        $this->assertSame('Manual Product', $manual->name_en);
        $this->assertSame(ProductSource::Manual, $manual->source);
    }

    public function test_phoenix_synced_products_have_phoenix_source(): void
    {
        $product = Product::query()->where('product_code', 'TMS-SHOE-001')->firstOrFail();

        $this->assertSame(ProductSource::Phoenix, $product->source);
    }
}
