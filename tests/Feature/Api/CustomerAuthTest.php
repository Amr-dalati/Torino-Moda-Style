<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_get_token(): void
    {
        $res = $this->postJson('/api/customer/register', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '+10000000001',
            'password' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'customer' => ['id', 'phone']]]);
    }

    public function test_customer_can_login_and_access_me(): void
    {
        Customer::factory()->create([
            'phone' => '+10000000002',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/customer/login', [
            'phone' => '+10000000002',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true);

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/customer/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone', '+10000000002');
    }

    public function test_customer_me_requires_customer_tokenable(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/customer/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_endpoints_without_token_return_401(): void
    {
        $this->getJson('/api/customer/me')->assertStatus(401);
        $this->postJson('/api/customer/logout')->assertStatus(401);
    }

    public function test_customer_logout_revokes_token(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+10000000003',
            'password' => 'password123',
        ]);

        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/customer/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/customer/me')
            ->assertStatus(401);
    }

    public function test_customer_token_cannot_access_user_me_or_logout(): void
    {
        $customer = Customer::factory()->create(['password' => 'password123']);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}

