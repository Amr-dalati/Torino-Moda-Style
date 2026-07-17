<?php

namespace App\Integrations\Payments;

/**
 * Normalized payment webhook payload from any provider gateway.
 */
final class PaymentWebhookEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $eventId,
        public readonly string $eventType,
        public readonly ?string $merchantReference,
        public readonly string $status,
        public readonly ?array $rawPayload = null,
        public readonly ?\DateTimeInterface $occurredAt = null,
    ) {}

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'succeeded', 'success'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'failure'], true);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'canceled'], true);
    }
}
