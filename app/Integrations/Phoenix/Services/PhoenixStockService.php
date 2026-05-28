<?php

namespace App\Integrations\Phoenix\Services;

use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Integrations\Phoenix\Contracts\PhoenixStockServiceInterface;

class PhoenixStockService implements PhoenixStockServiceInterface
{
    public function __construct(
        protected PhoenixClientInterface $client,
    ) {}

    public function fetchAll(): array
    {
        $response = $this->client->get('/api/stock');

        return $response['data'] ?? $response;
    }
}

