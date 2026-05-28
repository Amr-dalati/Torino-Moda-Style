<?php

namespace App\Integrations\Phoenix\Contracts;

interface PhoenixProductServiceInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function fetchById(string|int $phoenixId): ?array;
}
