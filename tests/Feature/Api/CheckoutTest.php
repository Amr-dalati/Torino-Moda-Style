<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    public function test_quote_success(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'is_default' => true,
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $res = $this->postJson('/api/customer/checkout/quote', [
            'address_id' => $address->id,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', 2598)
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.delivery_fee', 50);
    }

    public function test_quote_with_empty_cart_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $this->postJson('/api/customer/checkout/quote', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_quote_with_inactive_delivery_area_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->firstOrFail();
        $area->forceFill(['is_active' => false])->save();

        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/customer/checkout/quote', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_creates_order_and_payment_pending(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'recipient_phone' => '+10000000000',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $res = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.order_status', 'awaiting_payment')
            ->assertJsonPath('data.order.payment_status', 'pending')
            ->assertJsonPath('data.payment.status', 'pending');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_checkout_with_empty_cart_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_with_address_missing_delivery_area_id_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $address = $customer->addresses()->create([
            'delivery_area_id' => null,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_with_invalid_address_ownership_returns_404(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $addressB = $customerB->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'B Street',
        ]);

        Sanctum::actingAs($customerA);

        // Ensure cart not empty so we actually reach address ownership check.
        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/customer/checkout', [
            'address_id' => $addressB->id,
        ])->assertStatus(404);
    }

    public function test_checkout_with_inactive_delivery_area_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->firstOrFail();
        $area->forceFill(['is_active' => false])->save();

        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_above_stock_fails_422(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 12,
        ])->assertOk();

        // Force stock to lower than cart quantity.
        \App\Models\StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
        ]);

        $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_mock_payment_success_updates_statuses_and_checks_out_cart(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $orderId = $checkout['order']['id'];
        $merchantRef = $checkout['payment']['merchant_reference'];

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('awaiting_payment', $order->order_status);
        $this->assertSame('pending', $order->payment_status);

        $cart = Cart::query()->findOrFail($order->cart_id);
        $this->assertSame('active', $cart->status);

        // Mock payment success endpoint is local/testing only; tests run in testing.
        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $order->order_status);

        $cart->refresh();
        $this->assertSame('checked_out', $cart->status);
    }

    public function test_payment_success_is_idempotent(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertOk();

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertOk();

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_success_only_transitions_pending_payment(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $merchantRef = $checkout['payment']['merchant_reference'];

        // Force payment into a non-pending state.
        Payment::query()->where('merchant_reference', $merchantRef)->update(['status' => 'failed']);

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertStatus(422)->assertJsonPath('success', false);

        Payment::query()->where('merchant_reference', $merchantRef)->update(['status' => 'cancelled']);

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertStatus(422)->assertJsonPath('success', false);

        Payment::query()->where('merchant_reference', $merchantRef)->update(['status' => 'expired']);

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_customer_cannot_access_another_customers_order(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        Sanctum::actingAs($customerA);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $addressA = $customerA->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $addressA->id,
        ])->assertStatus(201)->json('data');

        $orderId = $checkout['order']['id'];

        Sanctum::actingAs($customerB);

        $this->getJson("/api/customer/orders/{$orderId}")->assertStatus(404);
    }

    public function test_payment_status_endpoint_returns_latest_payment(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $orderId = $checkout['order']['id'];

        $this->getJson("/api/customer/orders/{$orderId}/payment-status")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'pending');
    }
}

