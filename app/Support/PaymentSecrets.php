<?php

namespace App\Support;

use App\Integrations\Payments\Exceptions\PaymentConfigurationException;

class PaymentSecrets
{
    public static function mockWebhookSecret(): string
    {
        $secret = trim((string) config('payments.mock.webhook_secret', ''));

        if ($secret !== '') {
            return $secret;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'mock-webhook-secret';
        }

        throw PaymentConfigurationException::missing('MOCK_PAYMENT_WEBHOOK_SECRET');
    }

    /**
     * @return list<string>
     */
    public static function requiredKeysForProvider(string $provider): array
    {
        return match ($provider) {
            'mock' => ['MOCK_PAYMENT_WEBHOOK_SECRET'],
            'thawani' => [
                'THAWANI_SECRET_KEY',
                'THAWANI_PUBLISHABLE_KEY',
                'THAWANI_WEBHOOK_SECRET',
                'THAWANI_SUCCESS_URL',
                'THAWANI_CANCEL_URL',
            ],
            default => [],
        };
    }

    public static function assertProductionReady(): void
    {
        if (! app()->environment(['staging', 'production'])) {
            return;
        }

        $provider = (string) config('payments.provider', 'mock');

        if ($provider === 'mock') {
            throw PaymentConfigurationException::missing('PAYMENT_PROVIDER (mock is not allowed in staging/production)');
        }

        foreach (self::requiredKeysForProvider($provider) as $key) {
            self::assertEnvConfigured($provider, $key);
        }
    }

    protected static function assertEnvConfigured(string $provider, string $envKey): void
    {
        $configKey = match ($envKey) {
            'MOCK_PAYMENT_WEBHOOK_SECRET' => 'payments.mock.webhook_secret',
            'THAWANI_SECRET_KEY' => 'payments.thawani.secret_key',
            'THAWANI_PUBLISHABLE_KEY' => 'payments.thawani.publishable_key',
            'THAWANI_WEBHOOK_SECRET' => 'payments.thawani.webhook_secret',
            'THAWANI_SUCCESS_URL' => 'payments.thawani.success_url',
            'THAWANI_CANCEL_URL' => 'payments.thawani.cancel_url',
            default => null,
        };

        $value = $configKey !== null ? trim((string) config($configKey, '')) : '';

        if ($value === '') {
            throw PaymentConfigurationException::missing($envKey);
        }
    }
}
