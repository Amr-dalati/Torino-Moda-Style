<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_envelope(): void
    {
        $res = $this->getJson('/api/health');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'timestamp'],
                'meta',
                'errors',
            ]);
    }
}

