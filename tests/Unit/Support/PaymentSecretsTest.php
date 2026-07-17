<?php

namespace Tests\Unit\Support;

use App\Integrations\Payments\Exceptions\PaymentConfigurationException;
use App\Integrations\Payments\MockPaymentGateway;
use App\Support\PaymentSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_payment_works_in_testing_environment(): void
    {
        config(['payments.mock.webhook_secret' => null]);

        $secret = PaymentSecrets::mockWebhookSecret();

        $this->assertSame('mock-webhook-secret', $secret);

        $gateway = app(MockPaymentGateway::class);
        $signature = $gateway->signPayload('{"status":"paid"}');

        $this->assertNotEmpty($signature);
        $this->assertTrue($gateway->verifyWebhookSignature([
            'X-Mock-Signature' => $signature,
        ], '{"status":"paid"}'));
    }

    public function test_missing_mock_webhook_secret_fails_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['payments.mock.webhook_secret' => null]);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('MOCK_PAYMENT_WEBHOOK_SECRET');

        PaymentSecrets::mockWebhookSecret();
    }

    public function test_production_rejects_mock_payment_provider(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'payments.provider' => 'mock',
            'payments.mock.webhook_secret' => 'configured-secret',
        ]);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('PAYMENT_PROVIDER');

        PaymentSecrets::assertProductionReady();
    }

    public function test_secrets_are_never_included_in_api_responses(): void
    {
        config(['payments.mock.webhook_secret' => 'super-secret-value']);

        $response = $this->postJson('/api/payments/webhook/mock', [], [
            'X-Mock-Signature' => 'invalid',
        ]);

        $body = json_encode($response->json(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('super-secret-value', $body);
        $this->assertStringNotContainsString('mock-webhook-secret', $body);
    }

    public function test_secrets_are_never_included_in_sanitized_logs(): void
    {
        config(['payments.mock.webhook_secret' => 'super-secret-value']);

        Log::spy();

        $this->postJson('/api/payments/webhook/mock', [], [
            'X-Mock-Signature' => 'invalid',
        ]);

        Log::shouldNotHaveReceived('info', function (string $message) {
            return str_contains($message, 'super-secret-value');
        });
    }
}
