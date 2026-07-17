<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Services\Admin\OrderFulfillmentService;
use App\Services\Checkout\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    /**
     * @return array{on_hand: float, reserved: float, available: float}
     */
    protected function stockSummary(int $variantId): array
    {
        $levels = StockLevel::query()->where('product_variant_id', $variantId)->get();

        return [
            'on_hand' => $levels->sum(fn (StockLevel $level) => (float) $level->quantity_on_hand),
            'reserved' => $levels->sum(fn (StockLevel $level) => (float) $level->quantity_reserved),
            'available' => $levels->sum(
                fn (StockLevel $level) => (float) $level->quantity_on_hand - (float) $level->quantity_reserved,
            ),
        ];
    }

    protected function checkoutOneUnit(Customer $customer): array
    {
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

        return $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');
    }

    public function test_checkout_reserves_stock(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $before = $this->stockSummary($variant->id);

        $checkout = $this->checkoutOneUnit($customer);

        $after = $this->stockSummary($variant->id);
        $order = Order::query()->findOrFail($checkout['order']['id']);

        $this->assertSame($before['on_hand'], $after['on_hand']);
        $this->assertSame($before['reserved'] + 1, $after['reserved']);
        $this->assertSame($before['available'] - 1, $after['available']);
        $this->assertNotNull($order->stock_allocations);
        $this->assertNull($order->stock_committed_at);
        $this->assertNull($order->stock_released_at);
    }

    public function test_two_customers_cannot_reserve_more_than_available_stock(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $addressA = $customerA->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'A Street',
        ]);
        $addressB = $customerB->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'B Street',
        ]);

        // Both carts can hold items before any reservation; checkout serializes allocation.
        Sanctum::actingAs($customerA);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        Sanctum::actingAs($customerB);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        Sanctum::actingAs($customerA);
        $this->postJson('/api/customer/checkout', ['address_id' => $addressA->id])
            ->assertStatus(201);

        Sanctum::actingAs($customerB);
        $this->postJson('/api/customer/checkout', ['address_id' => $addressB->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $summary = $this->stockSummary($variant->id);
        $this->assertSame(3.0, $summary['on_hand']);
        $this->assertSame(2.0, $summary['reserved']);
        $this->assertSame(1.0, $summary['available']);
    }

    public function test_payment_success_decrements_on_hand_and_clears_reservation(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $afterReserve = $this->stockSummary($variant->id);
        $this->assertSame(5.0, $afterReserve['on_hand']);
        $this->assertSame(1.0, $afterReserve['reserved']);

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertOk();

        $afterPay = $this->stockSummary($variant->id);
        $order = Order::query()->findOrFail($checkout['order']['id']);

        $this->assertSame(4.0, $afterPay['on_hand']);
        $this->assertSame(0.0, $afterPay['reserved']);
        $this->assertSame(4.0, $afterPay['available']);
        $this->assertNotNull($order->fresh()->stock_committed_at);
    }

    public function test_duplicate_payment_success_does_not_decrement_twice(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postJson('/api/payments/mock/success', ['merchant_reference' => $merchantRef])->assertOk();
        $afterFirst = $this->stockSummary($variant->id);

        $this->postJson('/api/payments/mock/success', ['merchant_reference' => $merchantRef])->assertOk();
        $afterSecond = $this->stockSummary($variant->id);

        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
        $this->assertSame($afterFirst['reserved'], $afterSecond['reserved']);
        $this->assertSame(4.0, $afterSecond['on_hand']);
    }

    public function test_payment_failed_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $afterReserve = $this->stockSummary($variant->id);
        $this->assertSame(1.0, $afterReserve['reserved']);

        app(PaymentService::class)->markPaymentFailed($merchantRef, 'Card declined');

        $afterRelease = $this->stockSummary($variant->id);
        $order = Order::query()->findOrFail($checkout['order']['id']);

        $this->assertSame(5.0, $afterRelease['on_hand']);
        $this->assertSame(0.0, $afterRelease['reserved']);
        $this->assertSame(5.0, $afterRelease['available']);
        $this->assertNotNull($order->fresh()->stock_released_at);
        $this->assertNull($order->stock_committed_at);
    }

    public function test_idempotent_checkout_does_not_double_reserve(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/customer/checkout', ['address_id' => $address->id])->assertStatus(201);
        $afterFirst = $this->stockSummary($variant->id);

        $this->postJson('/api/customer/checkout', ['address_id' => $address->id])->assertStatus(201);
        $afterSecond = $this->stockSummary($variant->id);

        $this->assertSame($afterFirst['reserved'], $afterSecond['reserved']);
        $this->assertSame(1.0, $afterSecond['reserved']);
    }

    public function test_payment_expired_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $afterReserve = $this->stockSummary($variant->id);
        $this->assertSame(1.0, $afterReserve['reserved']);

        app(PaymentService::class)->markPaymentExpired($merchantRef);

        $afterRelease = $this->stockSummary($variant->id);
        $order = Order::query()->findOrFail($checkout['order']['id']);

        $this->assertSame(5.0, $afterRelease['on_hand']);
        $this->assertSame(0.0, $afterRelease['reserved']);
        $this->assertNotNull($order->fresh()->stock_released_at);
        $this->assertNull($order->stock_committed_at);
    }

    public function test_release_for_order_is_idempotent_on_second_call(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $order = Order::query()->findOrFail($checkout['order']['id']);
        $payments = app(PaymentService::class);

        $payments->markPaymentFailed($checkout['payment']['merchant_reference']);
        $afterFirst = $this->stockSummary($variant->id);

        $payments->markPaymentFailed($checkout['payment']['merchant_reference']);
        $afterSecond = $this->stockSummary($variant->id);

        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
        $this->assertSame($afterFirst['reserved'], $afterSecond['reserved']);
        $this->assertSame(0.0, $afterSecond['reserved']);
        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_expired_payment_does_not_release_committed_stock(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];
        $payments = app(PaymentService::class);

        $payments->markMockSuccess($merchantRef);
        $afterPay = $this->stockSummary($variant->id);
        $this->assertSame(4.0, $afterPay['on_hand']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $payments->markPaymentExpired($merchantRef);
    }

    public function test_paid_order_cancel_is_blocked_and_stock_remains_committed(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $checkout = $this->checkoutOneUnit($customer);
        app(PaymentService::class)->markMockSuccess($checkout['payment']['merchant_reference']);

        $afterPay = $this->stockSummary($variant->id);
        $this->assertSame(4.0, $afterPay['on_hand']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderFulfillmentService::class)->cancel($checkout['order']['id']);

        $afterAttempt = $this->stockSummary($variant->id);
        $this->assertSame(4.0, $afterAttempt['on_hand']);
        $this->assertSame(0.0, $afterAttempt['reserved']);
    }
}
