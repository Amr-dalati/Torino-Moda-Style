<?php

namespace App\Integrations\Payments\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * @return array{checkout_url: string, gateway_payment_id: string, expires_at: \DateTimeInterface|null, raw_payload?: array<string, mixed>}
     */
    public function createCheckout(Payment $payment): array;
}

