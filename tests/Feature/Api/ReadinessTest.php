<?php

namespace Tests\Feature\Api;

use App\Services\Ops\ReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_returns_component_statuses(): void
    {
        $this->getJson('/api/readiness')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checks' => [
                        '*' => ['name', 'status'],
                    ],
                ],
            ]);
    }

    public function test_readiness_returns_not_ready_when_database_fails(): void
    {
        $mock = Mockery::mock(ReadinessService::class);
        $mock->shouldReceive('assess')->andReturn([
            'status' => 'not_ready',
            'checks' => [
                ['name' => 'database', 'status' => 'fail'],
            ],
        ]);

        $this->app->instance(ReadinessService::class, $mock);

        $this->getJson('/api/readiness')
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'not_ready');
    }
}
