<?php

namespace App\Support\Production;

readonly class ProductionCheck
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
    ) {}
}
