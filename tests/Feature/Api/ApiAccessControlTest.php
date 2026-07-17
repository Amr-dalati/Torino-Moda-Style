<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');
    }

    public function test_customer_token_can_access_categories(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_customer_token_can_access_products(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_customer_token_cannot_access_stock_index(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/stock')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_customer_token_cannot_access_stock_by_product(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/stock/product/1')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_token_cannot_access_stock_by_warehouse(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/stock/warehouse/1')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_token_cannot_access_phoenix_health(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/phoenix/health')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_staff_token_can_access_stock_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/stock')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_staff_token_can_access_phoenix_health(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/phoenix/health')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthenticated_stock_request_returns_standard_envelope(): void
    {
        $this->getJson('/api/stock')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_tokenable_mismatch_returns_standard_envelope(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.');
    }
}
