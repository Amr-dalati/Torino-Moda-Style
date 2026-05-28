<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Admin\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makePaidOrder(string $orderStatus): Order
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        return Order::query()->create([
            'order_number' => 'TMS-TEST-'.uniqid(),
            'customer_id' => $customer->id,
            'order_status' => $orderStatus,
            'payment_status' => 'paid',
            'subtotal' => 100,
            'delivery_fee' => 10,
            'discount_total' => 0,
            'total' => 110,
            'currency' => 'EGP',
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
        ]);
    }

    public function test_paid_to_processing(): void
    {
        $order = $this->makePaidOrder('paid');

        $updated = app(OrderFulfillmentService::class)->markProcessing($order->id);

        $this->assertSame('processing', $updated->order_status);
    }

    public function test_processing_to_shipped(): void
    {
        $order = $this->makePaidOrder('processing');

        $updated = app(OrderFulfillmentService::class)->markShipped($order->id);

        $this->assertSame('shipped', $updated->order_status);
    }

    public function test_shipped_to_delivered(): void
    {
        $order = $this->makePaidOrder('shipped');

        $updated = app(OrderFulfillmentService::class)->markDelivered($order->id);

        $this->assertSame('delivered', $updated->order_status);
    }

    public function test_paid_can_be_cancelled(): void
    {
        $order = $this->makePaidOrder('paid');

        $updated = app(OrderFulfillmentService::class)->cancel($order->id);

        $this->assertSame('cancelled', $updated->order_status);
    }

    public function test_processing_can_be_cancelled(): void
    {
        $order = $this->makePaidOrder('processing');

        $updated = app(OrderFulfillmentService::class)->cancel($order->id);

        $this->assertSame('cancelled', $updated->order_status);
    }

    public function test_unpaid_order_is_forbidden_from_fulfillment(): void
    {
        $order = $this->makePaidOrder('paid');
        $order->forceFill(['payment_status' => 'pending'])->save();

        $this->expectException(ValidationException::class);

        app(OrderFulfillmentService::class)->markProcessing($order->id);
    }

    public function test_cannot_skip_transitions_paid_to_shipped(): void
    {
        $order = $this->makePaidOrder('paid');

        $this->expectException(ValidationException::class);

        app(OrderFulfillmentService::class)->markShipped($order->id);
    }

    public function test_cannot_cancel_shipped_or_delivered(): void
    {
        $shipped = $this->makePaidOrder('shipped');
        $delivered = $this->makePaidOrder('delivered');

        try {
            app(OrderFulfillmentService::class)->cancel($shipped->id);
            $this->fail('Expected ValidationException for shipped cancellation.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        app(OrderFulfillmentService::class)->cancel($delivered->id);
    }
}

