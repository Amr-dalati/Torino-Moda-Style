<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function assertEnvelopeKeys(array $json): void
    {
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('errors', $json);
    }

    public function test_customer_login_is_throttled_and_returns_429_envelope(): void
    {
        // Endpoint is throttled regardless of credentials; we only validate envelope + status.
        $res = null;
        for ($i = 0; $i < 6; $i++) {
            $res = $this->postJson('/api/customer/login', [
                'phone' => '01000000000',
                'password' => 'wrong',
                'device_name' => 'test',
            ]);
        }

        $res->assertStatus(429);
        $this->assertEnvelopeKeys($res->json());
        $res->assertJsonPath('success', false);
        $res->assertJsonPath('message', 'Too many requests.');
    }

    public function test_customer_register_is_throttled_and_returns_429_envelope(): void
    {
        $res = null;
        for ($i = 0; $i < 6; $i++) {
            $res = $this->postJson('/api/customer/register', [
                'name' => 'Test',
                'phone' => '01000000000',
                'password' => 'password',
                'password_confirmation' => 'password',
                'device_name' => 'test',
            ]);
        }

        $res->assertStatus(429);
        $this->assertEnvelopeKeys($res->json());
        $res->assertJsonPath('success', false);
        $res->assertJsonPath('message', 'Too many requests.');
    }

    public function test_checkout_is_throttled_and_returns_429_envelope(): void
    {
        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');

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

        $res = null;
        for ($i = 0; $i < 11; $i++) {
            $res = $this->postJson('/api/customer/checkout/quote', [
                'address_id' => $address->id,
            ]);
        }

        $res->assertStatus(429);
        $this->assertEnvelopeKeys($res->json());
        $res->assertJsonPath('message', 'Too many requests.');
    }

    public function test_cart_mutations_are_throttled_and_return_429_envelope(): void
    {
        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $variant = ProductVariant::query()->firstOrFail();

        $res = null;
        for ($i = 0; $i < 61; $i++) {
            $res = $this->postJson('/api/customer/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);
        }

        $res->assertStatus(429);
        $this->assertEnvelopeKeys($res->json());
        $res->assertJsonPath('message', 'Too many requests.');
    }

    public function test_mock_payment_is_throttled_and_returns_429_envelope(): void
    {
        $res = null;
        for ($i = 0; $i < 4; $i++) {
            $res = $this->postJson('/api/payments/mock/success', [
                'merchant_reference' => 'does-not-matter',
            ]);
        }

        $res->assertStatus(429);
        $this->assertEnvelopeKeys($res->json());
        $res->assertJsonPath('message', 'Too many requests.');
    }
}

