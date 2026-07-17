<?php

namespace App\Integrations\Payments\Exceptions;

use RuntimeException;

class UnsupportedPaymentProviderException extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self("Unsupported payment provider [{$provider}].");
    }
}
