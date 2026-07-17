<?php

namespace App\Integrations\Payments\Exceptions;

use RuntimeException;

class ThawaniApiException extends RuntimeException
{
    public static function fromResponse(string $message, ?int $status = null): self
    {
        $suffix = $status !== null ? " (HTTP {$status})" : '';

        return new self("Thawani API error: {$message}{$suffix}");
    }
}
