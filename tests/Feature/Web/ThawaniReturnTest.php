<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThawaniReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function createPendingPayment(): Payment
    {
        $customer = Customer::factory()->create();

        $order = Order::query()->create([
            'order_number' => 'TMS-2026-000099',
            'customer_id' => $customer->id,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => '10.00',
            'delivery_fee' => '1.00',
            'discount_total' => '0.00',
            'total' => '11.00',
            'currency' => 'OMR',
            'shipping_address_line1' => 'Test Street 1',
            'shipping_recipient_name' => 'Test Customer',
            'shipping_recipient_phone' => '+96890000000',
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'thawani',
            'method' => 'card',
            'amount' => '11.00',
            'currency' => 'OMR',
            'status' => 'pending',
            'merchant_reference' => 'mr_TMS-2026-000099',
            'gateway_payment_id' => 'sess_test_abc123',
            'checkout_url' => 'https://uatcheckout.thawani.om/pay/sess_test_abc123',
        ]);
    }

    public function test_success_return_redirects_to_configured_mobile_success_url_with_order_id(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'torinomodastyle://payment/success',
        ]);

        $payment = $this->createPendingPayment();

        $response = $this->get('/payments/thawani/success?session_id=sess_test_abc123');

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringStartsWith('torinomodastyle://payment/success', $target);
        $this->assertStringContainsString('order_id='.$payment->order_id, $target);
    }

    public function test_cancel_return_redirects_to_configured_mobile_cancel_url_with_order_id(): void
    {
        config([
            'payments.mobile.payment_cancel_url' => 'torinomodastyle://payment/cancel',
        ]);

        $payment = $this->createPendingPayment();

        $response = $this->get('/payments/thawani/cancel?client_reference_id=mr_TMS-2026-000099');

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringStartsWith('torinomodastyle://payment/cancel', $target);
        $this->assertStringContainsString('order_id='.$payment->order_id, $target);
    }

    public function test_return_route_does_not_change_payment_status(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'torinomodastyle://payment/success',
        ]);

        $payment = $this->createPendingPayment();

        $this->get('/payments/thawani/success?session_id=sess_test_abc123')->assertRedirect();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_invalid_identifiers_use_fallback_without_internal_errors(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'torinomodastyle://payment/success',
        ]);

        $response = $this->get('/payments/thawani/success?session_id=unknown_session');

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringStartsWith('torinomodastyle://payment/success', $target);
        $this->assertStringNotContainsString('order_id=', $target);
    }

    public function test_request_redirect_parameter_cannot_override_mobile_url(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'torinomodastyle://payment/success',
        ]);

        $this->createPendingPayment();

        $response = $this->get('/payments/thawani/success?session_id=sess_test_abc123&redirect=https://evil.example/phish');

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringStartsWith('torinomodastyle://payment/success', $target);
        $this->assertStringNotContainsString('evil.example', $target);
    }

    public function test_redirect_does_not_include_sensitive_payment_fields(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'torinomodastyle://payment/success',
        ]);

        $this->createPendingPayment();

        $response = $this->get('/payments/thawani/success?session_id=sess_test_abc123');

        $target = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('merchant_reference', $target);
        $this->assertStringNotContainsString('sess_test_abc123', $target);
        $this->assertStringNotContainsString('checkout_url', $target);
    }

    public function test_web_fallback_when_mobile_url_is_not_configured(): void
    {
        config([
            'payments.mobile.payment_success_url' => '',
        ]);

        $this->createPendingPayment();

        $response = $this->get('/payments/thawani/success?session_id=sess_test_abc123');

        $response->assertOk();
        $response->assertSee('Payment result received', false);
    }

    public function test_non_custom_scheme_mobile_url_uses_web_fallback(): void
    {
        config([
            'payments.mobile.payment_success_url' => 'https://evil.example/steal',
        ]);

        $this->createPendingPayment();

        $response = $this->get('/payments/thawani/success?session_id=sess_test_abc123');

        $response->assertOk();
        $response->assertSee('Payment result received', false);
    }
}
