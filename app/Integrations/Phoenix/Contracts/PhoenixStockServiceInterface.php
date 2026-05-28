<?php

namespace App\Integrations\Phoenix\Contracts;

interface PhoenixStockServiceInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array;
}

