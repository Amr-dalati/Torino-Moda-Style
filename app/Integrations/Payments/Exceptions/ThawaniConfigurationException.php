<?php

namespace App\Integrations\Payments\Exceptions;

use RuntimeException;

class ThawaniConfigurationException extends RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("Thawani payment provider is missing required configuration [{$key}].");
    }
}
