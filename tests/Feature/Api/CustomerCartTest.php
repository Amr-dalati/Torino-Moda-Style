<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('phoenix:sync');
    }

    public function test_unauthenticated_cart_access_returns_401(): void
    {
        $this->getJson('/api/customer/cart')->assertStatus(401);
    }

    public function test_user_token_on_customer_cart_route_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/customer/cart')->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_get_empty_active_cart(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->getJson('/api/customer/cart')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.subtotal', '0.00')
            ->assertJsonCount(0, 'data.items');
    }

    public function test_add_item_success_and_subtotal_recalculation(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $variant = ProductVariant::query()->firstOrFail();

        $res = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.subtotal', '2598.00');
    }

    public function test_adding_same_item_increments_quantity(): void
    {
        Sanctum::actingAs(Customer::factory()->create());
        $variant = ProductVariant::query()->firstOrFail();

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $res = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $res->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);
    }

    public function test_update_quantity_success(): void
    {
        Sanctum::actingAs(Customer::factory()->create());
        $variant = ProductVariant::query()->firstOrFail();

        $cart = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->json('data');

        $itemId = $cart['items'][0]['id'];

        $res = $this->putJson("/api/customer/cart/items/{$itemId}", [
            'quantity' => 2,
        ]);

        $res->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.subtotal', '2598.00');
    }

    public function test_update_quantity_above_stock_returns_422(): void
    {
        Sanctum::actingAs(Customer::factory()->create());
        $variant = ProductVariant::query()->firstOrFail();

        $cart = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->json('data');

        $itemId = $cart['items'][0]['id'];

        $this->putJson("/api/customer/cart/items/{$itemId}", [
            'quantity' => 999,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_remove_item(): void
    {
        Sanctum::actingAs(Customer::factory()->create());
        $variant = ProductVariant::query()->firstOrFail();

        $cart = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->json('data');

        $itemId = $cart['items'][0]['id'];

        $this->deleteJson("/api/customer/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.subtotal', '0.00');
    }

    public function test_clear_cart(): void
    {
        Sanctum::actingAs(Customer::factory()->create());
        $variant = ProductVariant::query()->firstOrFail();

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->deleteJson('/api/customer/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.subtotal', '0.00');
    }
}

