<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Support\PaymentSecrets;
use App\Models\Payment;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function providerName(): string
    {
        return 'mock';
    }

    public function createCheckout(Payment $payment): array
    {
        $gatewayPaymentId = 'mock_'.Str::uuid()->toString();

        $checkoutUrl = url('/mock-payments/checkout?ref='.urlencode($payment->merchant_reference));

        return [
            'checkout_url' => $checkoutUrl,
            'gateway_payment_id' => $gatewayPaymentId,
            'expires_at' => now()->addMinutes(30),
            'raw_payload' => [
                'provider' => 'mock',
                'merchant_reference' => $payment->merchant_reference,
            ],
        ];
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function verifyWebhookSignature(array $headers, string $payload): bool
    {
        $expected = $this->signPayload($payload);
        $provided = $this->headerValue($headers, config('payments.mock.signature_header', 'X-Mock-Signature'));

        if ($provided === null || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function parseWebhookEvent(array $headers, string $payload): PaymentWebhookEvent
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $status = (string) ($data['status'] ?? 'unknown');
        $eventType = (string) ($data['event_type'] ?? $this->eventTypeForStatus($status));

        $occurredAt = null;
        if (! empty($data['occurred_at'])) {
            $occurredAt = \Illuminate\Support\Carbon::parse((string) $data['occurred_at']);
        }

        return new PaymentWebhookEvent(
            provider: 'mock',
            eventId: isset($data['event_id']) ? (string) $data['event_id'] : null,
            eventType: $eventType,
            merchantReference: isset($data['merchant_reference']) ? (string) $data['merchant_reference'] : null,
            status: $status,
            rawPayload: $data,
            occurredAt: $occurredAt,
        );
    }

    public function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, PaymentSecrets::mockWebhookSecret());
    }

    protected function eventTypeForStatus(string $status): string
    {
        return match ($status) {
            'paid', 'succeeded', 'success' => 'payment_succeeded',
            'expired' => 'payment_expired',
            'cancelled', 'canceled' => 'payment_cancelled',
            default => 'payment_failed',
        };
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $lower) {
                continue;
            }

            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return $value;
        }

        return null;
    }
}
