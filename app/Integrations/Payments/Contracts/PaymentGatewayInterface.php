<?php

namespace App\Integrations\Payments\Contracts;

use App\Integrations\Payments\PaymentWebhookEvent;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function providerName(): string;

    /**
     * @return array{checkout_url: string, gateway_payment_id: string, expires_at: \DateTimeInterface|null, raw_payload?: array<string, mixed>}
     */
    public function createCheckout(Payment $payment): array;

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function verifyWebhookSignature(array $headers, string $payload): bool;

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function parseWebhookEvent(array $headers, string $payload): PaymentWebhookEvent;
}
