<?php

namespace Tests\Feature\Checkout;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\Checkout\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentPayloadRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_payment_success_payload_is_redacted_before_persisting(): void
    {
        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = \App\Models\DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
        ]);

        $variant = \App\Models\ProductVariant::query()->firstOrFail();
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $merchantRef = $checkout['payment']['merchant_reference'];

        // Call the service directly to control the payload being persisted.
        app(PaymentService::class)->markMockSuccess($merchantRef, [
            'Authorization' => 'Bearer should-not-store',
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'password' => 'pw',
        ]);

        $payment = Payment::query()->where('merchant_reference', $merchantRef)->firstOrFail();

        $this->assertIsArray($payment->raw_payload);
        $this->assertSame('[REDACTED]', $payment->raw_payload['Authorization'] ?? null);
        $this->assertSame('[REDACTED]', $payment->raw_payload['card_number'] ?? null);
        $this->assertSame('[REDACTED]', $payment->raw_payload['cvv'] ?? null);
        $this->assertSame('[REDACTED]', $payment->raw_payload['password'] ?? null);
    }
}

