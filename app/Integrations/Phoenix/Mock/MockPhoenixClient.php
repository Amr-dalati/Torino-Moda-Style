<?php

namespace App\Integrations\Phoenix\Mock;

use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Models\ApiIntegrationLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MockPhoenixClient implements PhoenixClientInterface
{
    public function get(string $path, array $query = []): array
    {
        return $this->respond('GET', $path, $query);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->respond('POST', $path, payload: $payload);
    }

    public function isHealthy(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function respond(string $method, string $path, array $query = [], array $payload = []): array
    {
        $correlationId = (string) Str::uuid();
        $fixture = $this->resolveFixture($path, $method);
        $data = $fixture !== null
            ? json_decode(File::get($fixture), true, flags: JSON_THROW_ON_ERROR)
            : ['data' => [], 'message' => 'Mock response (no fixture)'];

        ApiIntegrationLog::query()->create([
            'correlation_id' => $correlationId,
            'method' => strtoupper($method),
            'url' => $path,
            'status_code' => 200,
            'request_body' => $method === 'POST' ? $payload : $query,
            'response_body' => $data,
            'duration_ms' => 1,
            'is_mock' => true,
        ]);

        return $data;
    }

    protected function resolveFixture(string $path, string $method): ?string
    {
        $normalized = trim($path, '/');

        $map = [
            'api/health' => 'health.json',
            'api/products' => 'products.json',
            'api/stock' => 'stock.json',
            'api/customers' => 'customers.json',
        ];

        if ($method === 'POST' && $normalized === 'api/sales-orders') {
            return database_path('fixtures/phoenix/sales_order_created.json');
        }

        if (preg_match('#^api/products/\d+$#', $normalized)) {
            return database_path('fixtures/phoenix/product_detail.json');
        }

        return isset($map[$normalized])
            ? database_path('fixtures/phoenix/'.$map[$normalized])
            : null;
    }
}
