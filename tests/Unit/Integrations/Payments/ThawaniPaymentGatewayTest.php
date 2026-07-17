<?php

namespace Tests\Unit\Integrations\Payments;

use App\Integrations\Payments\Exceptions\ThawaniApiException;
use App\Integrations\Payments\Exceptions\ThawaniConfigurationException;
use App\Integrations\Payments\MockPaymentGateway;
use App\Integrations\Payments\PaymentGatewayResolver;
use App\Integrations\Payments\ThawaniPaymentGateway;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsThawaniWebhooks;
use Tests\TestCase;

class ThawaniPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;
    use SignsThawaniWebhooks;

    public function test_resolver_returns_thawani_gateway(): void
    {
        config($this->thawaniTestConfig());

        $gateway = app(PaymentGatewayResolver::class)->resolve('thawani');

        $this->assertInstanceOf(ThawaniPaymentGateway::class, $gateway);
        $this->assertSame('thawani', $gateway->providerName());
    }

    public function test_resolver_still_returns_mock_gateway(): void
    {
        config(['payments.provider' => 'mock']);

        $gateway = app(PaymentGatewayResolver::class)->resolve('mock');

        $this->assertInstanceOf(MockPaymentGateway::class, $gateway);
    }

    public function test_create_checkout_success(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => [
                    'session_id' => 'checkout_test_session_123',
                ],
            ], 200),
        ]);

        $payment = $this->makePayment();

        $gateway = app(ThawaniPaymentGateway::class);
        $result = $gateway->createCheckout($payment);

        $this->assertSame('checkout_test_session_123', $result['gateway_payment_id']);
        $this->assertStringContainsString('checkout_test_session_123', $result['checkout_url']);
        $this->assertStringContainsString('key=test-thawani-publishable', $result['checkout_url']);
        $this->assertNotNull($result['expires_at']);

        Http::assertSent(function ($request) use ($payment) {
            return $request->url() === 'https://uatcheckout.thawani.om/api/v1/checkout/session'
                && $request->hasHeader('thawani-api-key', 'test-thawani-secret')
                && $request['client_reference_id'] === $payment->merchant_reference
                && $request['mode'] === 'payment'
                && $request['success_url'] === 'https://example.test/payments/thawani/success';
        });
    }

    public function test_create_checkout_failure_throws_controlled_exception(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => false,
                'description' => 'Invalid products',
            ], 400),
        ]);

        $this->expectException(ThawaniApiException::class);
        $this->expectExceptionMessage('Invalid products');

        app(ThawaniPaymentGateway::class)->createCheckout($this->makePayment());
    }

    public function test_missing_config_throws_configuration_exception(): void
    {
        config([
            'payments.provider' => 'thawani',
            'payments.thawani' => [
                'secret_key' => '',
                'publishable_key' => '',
                'webhook_secret' => '',
                'base_url' => '',
                'checkout_base_url' => '',
                'success_url' => '',
                'cancel_url' => '',
            ],
        ]);

        $this->expectException(ThawaniConfigurationException::class);

        app(ThawaniPaymentGateway::class)->createCheckout($this->makePayment());
    }

    public function test_verify_and_parse_paid_webhook(): void
    {
        config($this->thawaniTestConfig());

        $gateway = app(ThawaniPaymentGateway::class);
        $payload = json_encode([
            'event_id' => 'evt_thawani_1',
            'event_type' => 'checkout.completed',
            'data' => [
                'session_id' => 'checkout_test_session_123',
                'client_reference_id' => 'mr_TMS-TEST-001',
                'payment_status' => 'paid',
                'created_at' => '2026-06-29T12:00:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $headers = ['thawani-signature' => [$gateway->signPayload($payload)]];

        $this->assertTrue($gateway->verifyWebhookSignature($headers, $payload));

        $event = $gateway->parseWebhookEvent($headers, $payload);
        $this->assertSame('thawani', $event->provider);
        $this->assertSame('evt_thawani_1', $event->eventId);
        $this->assertSame('mr_TMS-TEST-001', $event->merchantReference);
        $this->assertSame('paid', $event->status);
        $this->assertTrue($event->isPaid());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config($this->thawaniTestConfig());

        $gateway = app(ThawaniPaymentGateway::class);
        $payload = '{"event_id":"x"}';

        $this->assertFalse($gateway->verifyWebhookSignature(['thawani-signature' => ['bad']], $payload));
        $this->assertFalse($gateway->verifyWebhookSignature([], $payload));
    }

    public function test_normalize_unpaid_and_cancelled_statuses(): void
    {
        config($this->thawaniTestConfig());
        $gateway = app(ThawaniPaymentGateway::class);

        $unpaid = json_encode([
            'event_id' => 'evt_unpaid',
            'data' => [
                'client_reference_id' => 'mr_x',
                'payment_status' => 'unpaid',
            ],
        ], JSON_THROW_ON_ERROR);
        $event = $gateway->parseWebhookEvent([], $unpaid);
        $this->assertTrue($event->isFailed());

        $cancelled = json_encode([
            'event_id' => 'evt_cancel',
            'data' => [
                'client_reference_id' => 'mr_x',
                'payment_status' => 'cancelled',
            ],
        ], JSON_THROW_ON_ERROR);
        $event = $gateway->parseWebhookEvent([], $cancelled);
        $this->assertTrue($event->isCancelled());
    }

    public function test_create_checkout_with_delivery_fee_includes_delivery_product_line(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => ['session_id' => 'checkout_delivery'],
            ], 200),
        ]);

        $payment = $this->makePaymentWithItems(
            subtotal: '100.00',
            deliveryFee: '50.00',
            total: '150.00',
            paymentAmount: '150.00',
        );

        app(ThawaniPaymentGateway::class)->createCheckout($payment);

        Http::assertSent(function ($request) {
            $products = $request['products'];
            $sum = collect($products)->sum(fn (array $p) => $p['quantity'] * $p['unit_amount']);

            return $sum === 150000
                && collect($products)->contains(
                    fn (array $p) => $p['name'] === 'Delivery fee' && $p['unit_amount'] === 50000,
                );
        });
    }

    public function test_create_checkout_without_delivery_fee_matches_payment_amount(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => ['session_id' => 'checkout_no_delivery'],
            ], 200),
        ]);

        $payment = $this->makePaymentWithItems(
            subtotal: '100.00',
            deliveryFee: '0.00',
            total: '100.00',
            paymentAmount: '100.00',
        );

        app(ThawaniPaymentGateway::class)->createCheckout($payment);

        Http::assertSent(function ($request) {
            $sum = collect($request['products'])->sum(fn (array $p) => $p['quantity'] * $p['unit_amount']);

            return $sum === 100000
                && ! collect($request['products'])->contains(fn (array $p) => $p['name'] === 'Delivery fee');
        });
    }

    public function test_multi_quantity_item_product_sum_matches_payment_amount_exactly(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => ['session_id' => 'checkout_multi_qty'],
            ], 200),
        ]);

        $payment = $this->makePaymentWithItems(
            subtotal: '2598.00',
            deliveryFee: '50.00',
            total: '2648.00',
            paymentAmount: '2648.00',
            itemQuantity: 2,
            itemLineTotal: '2598.00',
        );

        app(ThawaniPaymentGateway::class)->createCheckout($payment);

        Http::assertSent(function ($request) {
            $sum = collect($request['products'])->sum(fn (array $p) => $p['quantity'] * $p['unit_amount']);

            return $sum === 2648000;
        });
    }

    public function test_amount_mismatch_throws_before_calling_thawani(): void
    {
        config($this->thawaniTestConfig());

        Http::fake();

        $payment = $this->makePaymentWithItems(
            subtotal: '100.00',
            deliveryFee: '50.00',
            total: '150.00',
            paymentAmount: '99.00',
        );

        $this->expectException(ThawaniApiException::class);
        $this->expectExceptionMessage('product total does not match payment amount');

        app(ThawaniPaymentGateway::class)->createCheckout($payment);

        Http::assertNothingSent();
    }

    public function test_discount_uses_single_product_line_for_exact_total(): void
    {
        config($this->thawaniTestConfig());

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session' => Http::response([
                'success' => true,
                'data' => ['session_id' => 'checkout_discount'],
            ], 200),
        ]);

        $payment = $this->makePaymentWithItems(
            subtotal: '100.00',
            deliveryFee: '50.00',
            discountTotal: '10.00',
            total: '140.00',
            paymentAmount: '140.00',
        );

        app(ThawaniPaymentGateway::class)->createCheckout($payment);

        Http::assertSent(function ($request) {
            $products = $request['products'];

            return count($products) === 1
                && $products[0]['quantity'] === 1
                && $products[0]['unit_amount'] === 140000;
        });
    }

    protected function makePaymentWithItems(
        string $subtotal,
        string $deliveryFee,
        string $total,
        string $paymentAmount,
        string $discountTotal = '0.00',
        int $itemQuantity = 1,
        string $itemLineTotal = '100.00',
    ): Payment {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'order_number' => 'TMS-2026-000100',
            'customer_id' => $customer->id,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount_total' => $discountTotal,
            'total' => $total,
            'currency' => 'OMR',
            'shipping_address_line1' => 'Test Street',
        ]);

        $catalog = $this->createTestProductVariant();

        $order->items()->create([
            'product_id' => $catalog['product_id'],
            'product_variant_id' => $catalog['variant_id'],
            'quantity' => $itemQuantity,
            'line_total' => $itemLineTotal,
            'unit_price_snapshot' => bcdiv($itemLineTotal, (string) max(1, $itemQuantity), 2),
            'product_name_en' => 'Test Shoe',
            'variant_sku' => 'SKU-TEST',
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'thawani',
            'method' => 'card',
            'amount' => $paymentAmount,
            'currency' => 'OMR',
            'status' => 'pending',
            'merchant_reference' => 'mr_TMS-2026-000100',
        ]);
    }

    protected function createTestProductVariant(): array
    {
        $product = Product::query()->create([
            'product_code' => 'TST-'.uniqid(),
            'name_en' => 'Test Shoe',
            'sale_price' => 100,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'phoenix_id' => 'PHX-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'is_active' => true,
        ]);

        return [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ];
    }

    protected function makePayment(): Payment
    {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'order_number' => 'TMS-2026-000099',
            'customer_id' => $customer->id,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => '100.00',
            'delivery_fee' => '0.00',
            'discount_total' => '0.00',
            'total' => '100.00',
            'currency' => 'OMR',
            'shipping_address_line1' => 'Test Street',
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'thawani',
            'method' => 'card',
            'amount' => '100.00',
            'currency' => 'OMR',
            'status' => 'pending',
            'merchant_reference' => 'mr_TMS-2026-000099',
        ]);
    }
}
