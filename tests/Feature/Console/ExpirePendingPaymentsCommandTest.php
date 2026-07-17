<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpirePendingPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    protected function createPendingPayment(?\DateTimeInterface $expiresAt = null): Payment
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $payment = Payment::query()->where('merchant_reference', $checkout['payment']['merchant_reference'])->firstOrFail();
        $payment->forceFill([
            'expires_at' => $expiresAt ?? now()->subMinute(),
        ])->save();

        return $payment->fresh();
    }

    public function test_command_expires_old_pending_payments_and_releases_stock(): void
    {
        $payment = $this->createPendingPayment();
        $variant = ProductVariant::query()->firstOrFail();
        $order = Order::query()->findOrFail($payment->order_id);

        $before = StockLevel::query()->where('product_variant_id', $variant->id)->get();
        $this->assertSame(1.0, $before->sum(fn ($l) => (float) $l->quantity_reserved));

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $payment->refresh();
        $order->refresh();
        $after = StockLevel::query()->where('product_variant_id', $variant->id)->get();

        $this->assertSame('expired', $payment->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('payment_failed', $order->order_status);
        $this->assertNotNull($order->stock_released_at);
        $this->assertSame(0.0, $after->sum(fn ($l) => (float) $l->quantity_reserved));
    }

    public function test_command_is_idempotent(): void
    {
        $payment = $this->createPendingPayment();
        $variant = ProductVariant::query()->firstOrFail();

        $this->artisan('payments:expire-pending')->assertSuccessful();
        $afterFirst = StockLevel::query()->where('product_variant_id', $variant->id)->get();

        $this->artisan('payments:expire-pending')->assertSuccessful();
        $afterSecond = StockLevel::query()->where('product_variant_id', $variant->id)->get();

        $this->assertSame(
            $afterFirst->sum(fn ($l) => (float) $l->quantity_on_hand),
            $afterSecond->sum(fn ($l) => (float) $l->quantity_on_hand),
        );
        $this->assertSame('expired', $payment->fresh()->status);
    }

    public function test_command_skips_future_expiry(): void
    {
        $payment = $this->createPendingPayment(now()->addHour());

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
