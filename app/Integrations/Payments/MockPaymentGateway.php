<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function createCheckout(Payment $payment): array
    {
        $gatewayPaymentId = 'mock_' . Str::uuid()->toString();

        // A fake checkout URL to simulate hosted payment page.
        // In real gateways this would be a hosted page or a client secret.
        $checkoutUrl = url('/mock-payments/checkout?ref=' . urlencode($payment->merchant_reference));

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
}

