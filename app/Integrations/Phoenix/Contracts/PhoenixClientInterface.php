<?php

namespace App\Integrations\Phoenix\Contracts;

interface PhoenixClientInterface
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array;

    public function isHealthy(): bool;
}
