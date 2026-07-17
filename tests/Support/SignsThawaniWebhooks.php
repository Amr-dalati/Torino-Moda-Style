<?php

namespace Tests\Support;

use App\Integrations\Payments\ThawaniPaymentGateway;

trait SignsThawaniWebhooks
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: string, signature: string, headers: array<string, string>}
     */
    protected function signedThawaniWebhook(array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $gateway = app(ThawaniPaymentGateway::class);

        return [
            'payload' => $json,
            'signature' => $gateway->signPayload($json),
            'headers' => [
                'thawani-signature' => $gateway->signPayload($json),
                'Content-Type' => 'application/json',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function thawaniTestConfig(): array
    {
        return [
            'payments.provider' => 'thawani',
            'payments.thawani' => [
                'secret_key' => 'test-thawani-secret',
                'publishable_key' => 'test-thawani-publishable',
                'webhook_secret' => 'test-thawani-webhook-secret',
                'base_url' => 'https://uatcheckout.thawani.om/api/v1',
                'checkout_base_url' => 'https://uatcheckout.thawani.om',
                'success_url' => 'https://example.test/payments/thawani/success',
                'cancel_url' => 'https://example.test/payments/thawani/cancel',
                'expiry_minutes' => 30,
            ],
        ];
    }

    protected function bindThawaniGateway(): void
    {
        config($this->thawaniTestConfig());
        $this->app->forgetInstance(\App\Integrations\Payments\Contracts\PaymentGatewayInterface::class);
        $this->app->forgetInstance(\App\Services\Checkout\PaymentService::class);
        $this->app->forgetInstance(\App\Services\Checkout\CheckoutService::class);
        $this->app->singleton(
            \App\Integrations\Payments\Contracts\PaymentGatewayInterface::class,
            fn () => app(ThawaniPaymentGateway::class),
        );
    }
}
