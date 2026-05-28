<?php

namespace App\Integrations\Phoenix;

use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Integrations\Phoenix\Exceptions\PhoenixApiException;
use App\Models\ApiIntegrationLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PhoenixClient implements PhoenixClientInterface
{
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, query: $query);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, payload: $payload);
    }

    public function isHealthy(): bool
    {
        try {
            $this->get('/api/health');

            return true;
        } catch (PhoenixApiException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $query = [], array $payload = []): array
    {
        $correlationId = (string) Str::uuid();
        $url = rtrim(config('phoenix.base_url'), '/').'/'.ltrim($path, '/');
        $startedAt = microtime(true);

        $request = $this->buildRequest();

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $payload),
            default => throw new PhoenixApiException("Unsupported HTTP method [{$method}]"),
        };

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        ApiIntegrationLog::query()->create([
            'correlation_id' => $correlationId,
            'method' => strtoupper($method),
            'url' => $url,
            'status_code' => $response->status(),
            'request_body' => $method === 'POST' ? $payload : $query,
            'response_body' => $response->json() ?? ['raw' => $response->body()],
            'duration_ms' => $durationMs,
            'is_mock' => false,
        ]);

        if ($response->failed()) {
            throw new PhoenixApiException(
                "Phoenix API request failed [{$response->status()}]",
                $response->status(),
                $response->json(),
            );
        }

        return $response->json() ?? [];
    }

    protected function buildRequest(): PendingRequest
    {
        $request = Http::timeout(config('phoenix.timeout'))
            ->acceptJson()
            ->asJson();

        if ($apiKey = config('phoenix.api_key')) {
            $request = $request->withHeaders(['X-Api-Key' => $apiKey]);
        }

        if (config('phoenix.username') && config('phoenix.password')) {
            $request = $request->withBasicAuth(
                (string) config('phoenix.username'),
                (string) config('phoenix.password'),
            );
        }

        return $request;
    }
}
