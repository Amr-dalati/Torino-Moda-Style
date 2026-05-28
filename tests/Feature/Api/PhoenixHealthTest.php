<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhoenixHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_phoenix_health_uses_mock(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/phoenix/health')
            ->assertOk()
            ->assertJsonPath('data.use_mock', true)
            ->assertJsonPath('data.phoenix_reachable', true);
    }
}
