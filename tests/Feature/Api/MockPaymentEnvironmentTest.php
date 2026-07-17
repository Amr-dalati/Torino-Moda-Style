<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockPaymentEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_payment_endpoint_is_blocked_outside_local_and_testing(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->postJson('/api/payments/mock/success')
                ->assertStatus(404);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
