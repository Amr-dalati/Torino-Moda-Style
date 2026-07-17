<?php

namespace Tests\Support;

use App\Integrations\Payments\MockPaymentGateway;

trait SignsMockPaymentWebhooks
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: string, signature: string, headers: array<string, string>}
     */
    protected function signedMockWebhook(array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $gateway = app(MockPaymentGateway::class);

        return [
            'payload' => $json,
            'signature' => $gateway->signPayload($json),
            'headers' => [
                'X-Mock-Signature' => $gateway->signPayload($json),
                'Content-Type' => 'application/json',
            ],
        ];
    }
}
