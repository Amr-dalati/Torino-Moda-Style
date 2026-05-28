<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Models\Cart;
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

    public function test_add_item_above_stock_returns_422(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $variant = ProductVariant::query()->firstOrFail();

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 999,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_add_item_invalid_variant_returns_422(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonPath('success', false);
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

    public function test_customer_cannot_update_or_delete_another_customers_cart_item(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $variant = ProductVariant::query()->firstOrFail();

        Sanctum::actingAs($customerA);

        $cart = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->json('data');

        $itemId = $cart['items'][0]['id'];

        Sanctum::actingAs($customerB);

        $this->putJson("/api/customer/cart/items/{$itemId}", [
            'quantity' => 2,
        ])->assertStatus(404);

        $this->deleteJson("/api/customer/cart/items/{$itemId}")
            ->assertStatus(404);
    }

    public function test_variant_without_sale_price_falls_back_to_product_price(): void
    {
        Sanctum::actingAs(Customer::factory()->create());

        $product = Product::query()->create([
            'product_code' => 'TMS-TEST-PRD-001',
            'barcode' => null,
            'name_en' => 'Test Product',
            'name_ar' => null,
            'sale_price' => 100,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'phoenix_id' => 'PHX-TEST-VAR-001',
            'sku' => 'TMS-TEST-PRD-001-V1',
            'barcode' => null,
            'sale_price' => null,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'TEST-WH',
            'name' => 'Test WH',
            'is_active' => true,
        ]);

        StockLevel::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $res = $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.unit_price_snapshot', '100.00')
            ->assertJsonPath('data.items.0.line_total', '200.00')
            ->assertJsonPath('data.subtotal', '200.00');
    }

    public function test_checked_out_cart_does_not_block_new_active_cart(): void
    {
        $customer = Customer::factory()->create();

        Cart::query()->create([
            'customer_id' => $customer->id,
            'status' => 'checked_out',
            'subtotal' => 10,
        ]);

        Sanctum::actingAs($customer);

        $res = $this->getJson('/api/customer/cart');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(2, Cart::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(1, Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->count());
    }
}

