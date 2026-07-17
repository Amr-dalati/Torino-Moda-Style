<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SignsMockPaymentWebhooks;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SignsMockPaymentWebhooks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    /**
     * @return array{on_hand: float, reserved: float}
     */
    protected function stockTotals(int $variantId): array
    {
        $levels = StockLevel::query()->where('product_variant_id', $variantId)->get();

        return [
            'on_hand' => $levels->sum(fn (StockLevel $l) => (float) $l->quantity_on_hand),
            'reserved' => $levels->sum(fn (StockLevel $l) => (float) $l->quantity_reserved),
        ];
    }

    protected function checkoutPayment(Customer $customer): array
    {
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'recipient_phone' => '+10000000000',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        return $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');
    }

    public function test_valid_paid_webhook_marks_payment_paid_and_commits_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $signed = $this->signedMockWebhook([
            'event_id' => 'evt_paid_1',
            'event_type' => 'payment_succeeded',
            'merchant_reference' => $merchantRef,
            'status' => 'paid',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid');

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $order = Order::query()->findOrFail($checkout['order']['id']);
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->fresh()->order_status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->stock_committed_at);
        $this->assertSame(9.0, $stock['on_hand']);
        $this->assertSame(0.0, $stock['reserved']);
    }

    public function test_duplicate_paid_webhook_does_not_double_commit_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = [
            'event_id' => 'evt_paid_dup',
            'event_type' => 'payment_succeeded',
            'merchant_reference' => $merchantRef,
            'status' => 'paid',
        ];

        $signed = $this->signedMockWebhook($payload);
        $headers = $this->transformHeadersToServerVars($signed['headers']);

        $this->call('POST', '/api/payments/webhook/mock', [], [], [], $headers, $signed['payload'])->assertOk();
        $afterFirst = $this->stockTotals($variant->id);

        $signedAgain = $this->signedMockWebhook($payload);
        $this->call('POST', '/api/payments/webhook/mock', [], [], [], $headers, $signedAgain['payload'])
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $afterSecond = $this->stockTotals($variant->id);
        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
        $this->assertSame(9.0, $afterSecond['on_hand']);
        $this->assertSame(2, PaymentWebhook::query()->count());
    }

    public function test_valid_failed_webhook_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $signed = $this->signedMockWebhook([
            'event_id' => 'evt_failed_1',
            'merchant_reference' => $merchantRef,
            'status' => 'failed',
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        )->assertOk();

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $order = Order::query()->findOrFail($checkout['order']['id']);
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('failed', $payment->status);
        $this->assertSame('payment_failed', $order->fresh()->order_status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->stock_released_at);
        $this->assertSame(10.0, $stock['on_hand']);
        $this->assertSame(0.0, $stock['reserved']);
    }

    public function test_valid_expired_webhook_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $signed = $this->signedMockWebhook([
            'event_id' => 'evt_expired_1',
            'merchant_reference' => $merchantRef,
            'status' => 'expired',
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        )->assertOk();

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $order = Order::query()->findOrFail($checkout['order']['id']);
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('expired', $payment->status);
        $this->assertSame('payment_failed', $order->fresh()->order_status);
        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->stock_released_at);
        $this->assertSame(10.0, $stock['on_hand']);
        $this->assertSame(0.0, $stock['reserved']);
    }

    public function test_invalid_signature_does_not_update_payment_or_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = json_encode([
            'event_id' => 'evt_bad_sig',
            'merchant_reference' => $merchantRef,
            'status' => 'paid',
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Mock-Signature' => 'invalid',
                'Content-Type' => 'application/json',
            ]),
            $payload,
        )->assertStatus(401);

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('pending', $payment->status);
        $this->assertSame(10.0, $stock['on_hand']);
        $this->assertSame(1.0, $stock['reserved']);
        $this->assertSame(1, PaymentWebhook::query()->where('signature_valid', false)->count());
    }

    public function test_unknown_merchant_reference_is_stored_safely(): void
    {
        $signed = $this->signedMockWebhook([
            'event_id' => 'evt_unknown_ref',
            'merchant_reference' => 'mr_unknown_ref',
            'status' => 'paid',
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        )->assertOk()
            ->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_event_id' => 'evt_unknown_ref',
            'processing_status' => 'ignored',
        ]);
    }

    public function test_mock_payment_success_endpoint_still_works(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postJson('/api/payments/mock/success', [
            'merchant_reference' => $merchantRef,
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_unsupported_provider_returns_404(): void
    {
        $this->postJson('/api/payments/webhook/stripe', ['status' => 'paid'])
            ->assertStatus(404);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $signed = $this->signedMockWebhook($payload);

        return $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        );
    }

    public function test_failed_webhook_with_unknown_merchant_reference_is_ignored_safely(): void
    {
        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $beforeStock = $this->stockTotals($variant->id);

        $this->postSignedWebhook([
            'event_id' => 'evt_unknown_failed',
            'merchant_reference' => 'mr_does_not_exist',
            'status' => 'failed',
        ])->assertOk()
            ->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_event_id' => 'evt_unknown_failed',
            'processing_status' => 'ignored',
        ]);
        $this->assertSame(0, Payment::query()->where('merchant_reference', 'mr_does_not_exist')->count());
        $this->assertSame($beforeStock, $this->stockTotals($variant->id));
    }

    public function test_expired_webhook_with_unknown_merchant_reference_is_ignored_safely(): void
    {
        $variant = ProductVariant::query()->firstOrFail();
        $beforeStock = $this->stockTotals($variant->id);

        $this->postSignedWebhook([
            'event_id' => 'evt_unknown_expired',
            'merchant_reference' => 'mr_does_not_exist',
            'status' => 'expired',
        ])->assertOk()
            ->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_event_id' => 'evt_unknown_expired',
            'processing_status' => 'ignored',
        ]);
        $this->assertSame($beforeStock, $this->stockTotals($variant->id));
    }

    public function test_cancelled_webhook_with_unknown_merchant_reference_is_ignored_safely(): void
    {
        $this->postSignedWebhook([
            'event_id' => 'evt_unknown_cancelled',
            'merchant_reference' => 'mr_does_not_exist',
            'status' => 'cancelled',
        ])->assertOk()
            ->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_event_id' => 'evt_unknown_cancelled',
            'processing_status' => 'ignored',
        ]);
        $this->assertSame(0, Payment::query()->where('merchant_reference', 'mr_does_not_exist')->count());
    }

    public function test_duplicate_retry_of_ignored_webhook_does_not_crash(): void
    {
        $payload = [
            'event_id' => 'evt_unknown_retry',
            'merchant_reference' => 'mr_unknown_retry',
            'status' => 'paid',
        ];

        $this->postSignedWebhook($payload)->assertOk()->assertJsonPath('data.processed', false);
        $this->postSignedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(2, PaymentWebhook::query()->count());
        $this->assertSame(1, PaymentWebhook::query()->where('gateway_event_id', 'evt_unknown_retry')->count());
    }

    public function test_duplicate_failed_webhook_does_not_release_stock_twice(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = [
            'event_id' => 'evt_failed_dup',
            'merchant_reference' => $merchantRef,
            'status' => 'failed',
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $afterFirst = $this->stockTotals($variant->id);

        $this->postSignedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $afterSecond = $this->stockTotals($variant->id);
        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
        $this->assertSame(0.0, $afterSecond['reserved']);
    }

    public function test_duplicate_expired_webhook_does_not_release_stock_twice(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutPayment($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = [
            'event_id' => 'evt_expired_dup',
            'merchant_reference' => $merchantRef,
            'status' => 'expired',
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $afterFirst = $this->stockTotals($variant->id);

        $this->postSignedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $afterSecond = $this->stockTotals($variant->id);
        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
        $this->assertSame(0.0, $afterSecond['reserved']);
    }
}
