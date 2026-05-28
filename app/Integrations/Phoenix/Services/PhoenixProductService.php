<?php

namespace App\Integrations\Phoenix\Services;

use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Integrations\Phoenix\Contracts\PhoenixProductServiceInterface;

class PhoenixProductService implements PhoenixProductServiceInterface
{
    public function __construct(
        protected PhoenixClientInterface $client,
    ) {}

    public function fetchAll(): array
    {
        $response = $this->client->get('/api/products');

        return $response['data'] ?? $response;
    }

    public function fetchById(string|int $phoenixId): ?array
    {
        $response = $this->client->get("/api/products/{$phoenixId}");

        return $response['data'] ?? $response;
    }
}
