<?php

namespace Tests\Unit\Services\Stock;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Stock\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockReservationService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockReservationService::class);
    }

    protected function makeOrderWithItem(int $quantity = 1): Order
    {
        $product = Product::query()->create([
            'product_code' => 'TST-'.uniqid(),
            'name_en' => 'Test',
            'sale_price' => 10,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'phoenix_id' => 'PHX-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-'.uniqid(),
            'name' => 'Test WH',
            'is_active' => true,
        ]);

        StockLevel::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $customer = Customer::factory()->create();

        $order = Order::query()->create([
            'order_number' => 'TMS-TEST-'.uniqid(),
            'customer_id' => $customer->id,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'delivery_fee' => 0,
            'discount_total' => 0,
            'total' => 10,
            'currency' => config('app.currency'),
            'shipping_address_line1' => 'Street',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price_snapshot' => '10.00',
            'line_total' => '10.00',
        ]);

        return $order->fresh()->load('items');
    }

    public function test_reserve_is_idempotent(): void
    {
        $order = $this->makeOrderWithItem();

        $this->stock->reserveForOrder($order);
        $this->stock->reserveForOrder($order->fresh());

        $level = StockLevel::query()->where('product_variant_id', $order->items->first()->product_variant_id)->firstOrFail();
        $this->assertSame(1.0, (float) $level->quantity_reserved);
    }

    public function test_reserve_fails_when_insufficient_stock(): void
    {
        $order = $this->makeOrderWithItem(quantity: 20);

        $this->expectException(ValidationException::class);
        $this->stock->reserveForOrder($order);
    }

    public function test_release_restores_available_quantity(): void
    {
        $order = $this->makeOrderWithItem();
        $variantId = $order->items->first()->product_variant_id;

        $this->stock->reserveForOrder($order);
        $this->assertSame(9.0, $this->stock->availableQuantityForVariant($variantId));

        $this->stock->releaseForOrder($order->fresh());
        $this->assertSame(10.0, $this->stock->availableQuantityForVariant($variantId));
    }

    public function test_commit_is_idempotent(): void
    {
        $order = $this->makeOrderWithItem();
        $variantId = $order->items->first()->product_variant_id;

        $this->stock->reserveForOrder($order);
        $this->stock->commitForPaidOrder($order->fresh());
        $this->stock->commitForPaidOrder($order->fresh());

        $level = StockLevel::query()->where('product_variant_id', $variantId)->firstOrFail();
        $this->assertSame(9.0, (float) $level->quantity_on_hand);
        $this->assertSame(0.0, (float) $level->quantity_reserved);
    }
}
