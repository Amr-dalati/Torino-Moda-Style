<?php

namespace Tests\Feature\Console;

use App\Support\Ops\SchedulerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_test_runs_in_testing_environment(): void
    {
        config(['app.url' => 'http://localhost']);

        Http::fake([
            'http://localhost/api/health' => Http::response([
                'success' => true,
                'data' => ['status' => 'ok'],
            ], 200),
            'http://localhost/api/readiness' => Http::response([
                'success' => true,
                'data' => ['status' => 'ready', 'checks' => []],
            ], 200),
            'http://localhost/legal/*' => Http::response('<html>ok</html>', 200),
        ]);

        Cache::put(SchedulerHeartbeat::CACHE_KEY, now()->toIso8601String(), now()->addHour());

        $this->artisan('app:smoke-test')
            ->assertExitCode(0);
    }

    public function test_smoke_test_fails_when_health_unreachable(): void
    {
        config(['app.url' => 'http://localhost']);

        Http::fake([
            'http://localhost/api/health' => Http::response([], 500),
            'http://localhost/api/readiness' => Http::response([
                'success' => true,
                'data' => ['status' => 'ready', 'checks' => []],
            ], 200),
            'http://localhost/legal/*' => Http::response('<html>ok</html>', 200),
        ]);

        $this->artisan('app:smoke-test')
            ->assertExitCode(1);
    }

    public function test_smoke_test_output_does_not_include_secrets(): void
    {
        config([
            'app.url' => 'http://localhost',
            'payments.thawani.secret_key' => 'super-secret-thawani-key',
        ]);

        Http::fake([
            'http://localhost/api/health' => Http::response(['success' => true, 'data' => ['status' => 'ok']], 200),
            'http://localhost/api/readiness' => Http::response(['success' => true, 'data' => ['status' => 'ready', 'checks' => []]], 200),
            'http://localhost/legal/*' => Http::response('<html>ok</html>', 200),
        ]);

        $this->artisan('app:smoke-test')
            ->expectsOutputToContain('PASS')
            ->doesntExpectOutputToContain('super-secret-thawani-key');
    }
}
