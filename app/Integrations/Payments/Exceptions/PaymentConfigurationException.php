<?php

namespace App\Integrations\Payments\Exceptions;

use RuntimeException;

class PaymentConfigurationException extends RuntimeException
{
    public static function missing(string $configurationKey): self
    {
        return new self("Payment configuration is incomplete. Set {$configurationKey}.");
    }
}
