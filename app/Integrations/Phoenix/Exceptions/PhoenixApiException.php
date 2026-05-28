<?php

namespace App\Integrations\Phoenix\Exceptions;

use Exception;

class PhoenixApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
