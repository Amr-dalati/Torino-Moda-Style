<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_access_me(): void
    {
        User::factory()->create([
            'email' => 'rep@test.com',
            'password' => 'secret123',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'rep@test.com',
            'password' => 'secret123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rep@test.com');
    }

    public function test_invalid_credentials_return_validation_error(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@test.com',
            'password' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
