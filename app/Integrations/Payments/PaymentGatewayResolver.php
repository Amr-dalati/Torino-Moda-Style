<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\Exceptions\UnsupportedPaymentProviderException;

class PaymentGatewayResolver
{
    public function resolve(?string $provider = null): PaymentGatewayInterface
    {
        $provider = $provider ?? (string) config('payments.provider', 'mock');

        return match ($provider) {
            'mock' => app(MockPaymentGateway::class),
            'thawani' => app(ThawaniPaymentGateway::class),
            default => throw UnsupportedPaymentProviderException::forProvider($provider),
        };
    }
}
