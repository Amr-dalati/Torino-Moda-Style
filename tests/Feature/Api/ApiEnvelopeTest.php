<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function assertEnvelope(array $json): void
    {
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('errors', $json);
    }

    public function test_401_returns_envelope(): void
    {
        $res = $this->getJson('/api/products');

        $res->assertStatus(401);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
    }

    public function test_403_tokenable_forbidden_returns_envelope(): void
    {
        $this->artisan('db:seed');

        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/customer/cart');

        $res->assertStatus(403);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
    }

    public function test_404_returns_envelope(): void
    {
        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $res = $this->getJson('/api/products/999999999');

        $res->assertStatus(404);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
    }

    public function test_422_returns_envelope(): void
    {
        $res = $this->postJson('/api/customer/login', []);

        $res->assertStatus(422);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
        $res->assertJsonPath('message', 'Validation failed');
    }

    public function test_paginated_success_has_envelope_and_meta(): void
    {
        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $res = $this->getJson('/api/products?per_page=1');

        $res->assertOk();
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('errors', null);
        $res->assertJsonStructure([
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
    }

    public function test_safe_500_envelope_for_generic_throwable(): void
    {
        Route::get('/api/__test/boom', function () {
            throw new \RuntimeException('boom');
        });

        $res = $this->getJson('/api/__test/boom');

        $res->assertStatus(500);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
        $res->assertJsonPath('message', 'Server error.');
    }

    public function test_safe_500_envelope_for_query_exception(): void
    {
        Route::get('/api/__test/query-exception', function () {
            DB::select('select * from table_that_does_not_exist');
        });

        $res = $this->getJson('/api/__test/query-exception');

        $res->assertStatus(500);
        $this->assertEnvelope($res->json());
        $res->assertJsonPath('success', false);
        $res->assertJsonPath('message', 'Server error.');
    }
}

