<?php

namespace Tests\Feature\Api;

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

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_products_search_returns_results(): void
    {
        $res = $this->getJson('/api/products/search?q=Classic');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_code', 'TMS-SHOE-001');
    }

    public function test_products_barcode_returns_variant_match(): void
    {
        $res = $this->getJson('/api/products/barcode/6281001001018');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.product_code', 'TMS-SHOE-001')
            ->assertJsonPath('data.variant.barcode', '6281001001018');
    }
}

