<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Admin\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTotalsImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfillment_transitions_do_not_change_totals(): void
    {
        $customer = Customer::factory()->create();

        /** @var Order $order */
        $order = Order::query()->create([
            'order_number' => 'TMS-IMMUTABLE-1',
            'customer_id' => $customer->id,
            'order_status' => 'paid',
            'payment_status' => 'paid',
            'subtotal' => '100.00',
            'delivery_fee' => '10.00',
            'discount_total' => '0.00',
            'total' => '110.00',
            'currency' => config('app.currency'),
            'shipping_address_line1' => 'Street 1',
            'shipping_recipient_name' => 'Test',
            'shipping_recipient_phone' => '01000000000',
            'shipping_city' => 'Cairo',
            'shipping_area_name' => 'Area',
            'shipping_delivery_region_code' => 'RG',
            'shipping_delivery_area_code' => 'AR',
        ]);

        $before = $order->only(['subtotal', 'delivery_fee', 'discount_total', 'total', 'currency']);

        $service = app(OrderFulfillmentService::class);
        $service->markProcessing($order->id);
        $service->markShipped($order->id);
        $service->markDelivered($order->id);

        $order->refresh();
        $after = $order->only(['subtotal', 'delivery_fee', 'discount_total', 'total', 'currency']);

        $this->assertSame($before, $after);
    }
}

