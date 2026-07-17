<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Services\Admin\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancellationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    protected function makeOrder(array $overrides = []): Order
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        return Order::query()->create(array_merge([
            'order_number' => 'TMS-TEST-'.uniqid(),
            'customer_id' => $customer->id,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'delivery_fee' => 10,
            'discount_total' => 0,
            'total' => 110,
            'currency' => config('app.currency'),
            'shipping_label' => 'Home',
            'shipping_recipient_name' => 'Test',
            'shipping_recipient_phone' => '01000000000',
            'shipping_address_line1' => 'Street 1',
            'shipping_city' => 'Cairo',
            'shipping_area_name' => 'Area',
            'shipping_postal_code' => '00000',
            'shipping_delivery_region_code' => 'RG',
            'shipping_delivery_area_code' => 'AR',
            'shipping_delivery_area_id' => null,
            'customer_address_id' => null,
            'cart_id' => null,
            'phoenix_order_id' => null,
            'sync_status' => null,
            'sync_attempts' => 0,
            'last_sync_error' => null,
        ], $overrides));
    }

    public function test_pending_unpaid_order_cancel_releases_stock_once(): void
    {
        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 1,
        ]);

        $order = $this->makeOrder([
            'stock_allocations' => [[
                'product_variant_id' => $variant->id,
                'warehouse_id' => StockLevel::query()->where('product_variant_id', $variant->id)->value('warehouse_id'),
                'quantity' => 1,
            ]],
        ]);

        $service = app(OrderFulfillmentService::class);
        $cancelled = $service->cancelUnpaid($order->id);

        $this->assertSame('cancelled', $cancelled->order_status);
        $this->assertSame('cancelled', $cancelled->payment_status);
        $this->assertNotNull($cancelled->fresh()->stock_released_at);

        $level = StockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame('0.000', $level->quantity_reserved);

        $this->expectException(ValidationException::class);
        $service->cancelUnpaid($order->id);
    }

    public function test_failed_payment_order_can_be_cancelled(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'payment_failed',
            'payment_status' => 'failed',
        ]);

        $cancelled = app(OrderFulfillmentService::class)->cancelUnpaid($order->id);

        $this->assertSame('cancelled', $cancelled->order_status);
        $this->assertSame('failed', $cancelled->payment_status);
    }

    public function test_expired_payment_order_can_be_cancelled(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'awaiting_payment',
            'payment_status' => 'expired',
        ]);

        $cancelled = app(OrderFulfillmentService::class)->cancelUnpaid($order->id);

        $this->assertSame('cancelled', $cancelled->order_status);
        $this->assertSame('expired', $cancelled->payment_status);
    }

    public function test_paid_order_cannot_be_cancelled_via_simple_action(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'paid',
            'payment_status' => 'paid',
            'stock_committed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancelUnpaid($order->id);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancel($order->id);
    }

    public function test_processing_paid_order_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'stock_committed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancel($order->id);
    }

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'shipped',
            'payment_status' => 'paid',
            'stock_committed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancelUnpaid($order->id);
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'stock_committed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancelUnpaid($order->id);
    }

    public function test_already_cancelled_order_cannot_be_cancelled_again(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancelUnpaid($order->id);
    }
}
