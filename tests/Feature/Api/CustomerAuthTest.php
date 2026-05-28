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
}

