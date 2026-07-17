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
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SignsThawaniWebhooks;
use Tests\TestCase;

class ThawaniPaymentTest extends TestCase
{
    use RefreshDatabase;
    use SignsThawaniWebhooks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
        $this->bindThawaniGateway();
    }

    protected function fakeThawaniSessionSuccess(string $sessionId = 'checkout_feat_session_1'): void
    {
        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => ['session_id' => $sessionId],
            ], 200),
        ]);
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

    protected function checkoutWithThawani(Customer $customer): array
    {
        $this->fakeThawaniSessionSuccess();

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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postThawaniWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $signed = $this->signedThawaniWebhook($payload);

        return $this->call(
            'POST',
            '/api/payments/webhook/thawani',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        );
    }

    public function test_checkout_creates_thawani_payment_with_session_and_checkout_url(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);

        $payment = Payment::query()->where('merchant_reference', $checkout['payment']['merchant_reference'])->firstOrFail();

        $this->assertSame('thawani', $payment->provider);
        $this->assertSame('checkout_feat_session_1', $payment->gateway_payment_id);
        $this->assertStringContainsString('checkout_feat_session_1', (string) $payment->checkout_url);
        $this->assertStringContainsString('key=test-thawani-publishable', (string) $payment->checkout_url);
        $this->assertSame('thawani', $checkout['payment']['provider']);

        Http::assertSent(function ($request) use ($checkout) {
            $expectedBaisa = (int) round((float) $checkout['payment']['amount'] * 1000);
            $sum = collect($request['products'])->sum(fn (array $p) => $p['quantity'] * $p['unit_amount']);

            return $sum === $expectedBaisa;
        });

        Http::assertSentCount(1);
    }

    public function test_checkout_failure_rolls_back_order_when_thawani_fails(): void
    {
        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => false,
                'description' => 'Gateway unavailable',
            ], 503),
        ]);

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

        $this->postJson('/api/customer/checkout', ['address_id' => $address->id])
            ->assertStatus(500);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_valid_paid_thawani_webhook_marks_payment_paid_and_commits_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postThawaniWebhook([
            'event_id' => 'evt_thawani_paid_1',
            'event_type' => 'checkout.completed',
            'data' => [
                'session_id' => 'checkout_feat_session_1',
                'client_reference_id' => $merchantRef,
                'payment_status' => 'paid',
            ],
        ])->assertOk()->assertJsonPath('data.payment_status', 'paid');

        $order = Order::query()->findOrFail($checkout['order']['id']);
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('paid', $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->stock_committed_at);
        $this->assertSame(9.0, $stock['on_hand']);
        $this->assertSame(0.0, $stock['reserved']);
    }

    public function test_duplicate_paid_thawani_webhook_does_not_double_commit_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = [
            'event_id' => 'evt_thawani_paid_dup',
            'data' => [
                'client_reference_id' => $merchantRef,
                'payment_status' => 'paid',
            ],
        ];

        $this->postThawaniWebhook($payload)->assertOk();
        $afterFirst = $this->stockTotals($variant->id);

        $this->postThawaniWebhook($payload)->assertOk()->assertJsonPath('data.duplicate', true);
        $afterSecond = $this->stockTotals($variant->id);

        $this->assertSame($afterFirst['on_hand'], $afterSecond['on_hand']);
    }

    public function test_unpaid_thawani_webhook_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postThawaniWebhook([
            'event_id' => 'evt_thawani_unpaid',
            'data' => [
                'client_reference_id' => $merchantRef,
                'payment_status' => 'unpaid',
            ],
        ])->assertOk();

        $order = Order::query()->findOrFail($checkout['order']['id']);
        $stock = $this->stockTotals($variant->id);

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame('payment_failed', $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->stock_released_at);
        $this->assertSame(10.0, $stock['on_hand']);
        $this->assertSame(0.0, $stock['reserved']);
    }

    public function test_cancelled_thawani_webhook_releases_reservation(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        $this->postThawaniWebhook([
            'event_id' => 'evt_thawani_cancel',
            'data' => [
                'client_reference_id' => $merchantRef,
                'payment_status' => 'cancelled',
            ],
        ])->assertOk();

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();
        $this->assertSame('failed', $payment->status);
    }

    public function test_invalid_thawani_signature_does_not_update_payment_or_stock(): void
    {
        $customer = Customer::factory()->create();
        $checkout = $this->checkoutWithThawani($customer);
        $variant = ProductVariant::query()->firstOrFail();
        $merchantRef = $checkout['payment']['merchant_reference'];

        $payload = json_encode([
            'event_id' => 'evt_bad',
            'data' => [
                'client_reference_id' => $merchantRef,
                'payment_status' => 'paid',
            ],
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/thawani',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'thawani-signature' => 'invalid',
                'Content-Type' => 'application/json',
            ]),
            $payload,
        )->assertStatus(401);

        $this->assertSame('pending', Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail()->status);
        $this->assertSame(1.0, $this->stockTotals($variant->id)['reserved']);
    }

    public function test_unknown_client_reference_is_ignored_safely(): void
    {
        $this->postThawaniWebhook([
            'event_id' => 'evt_thawani_unknown',
            'data' => [
                'client_reference_id' => 'mr_unknown',
                'payment_status' => 'paid',
            ],
        ])->assertOk()->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('payment_webhooks', [
            'provider' => 'thawani',
            'gateway_event_id' => 'evt_thawani_unknown',
            'processing_status' => 'ignored',
        ]);
    }
}
