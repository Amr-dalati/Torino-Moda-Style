<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_requires_auth(): void
    {
        $this->putJson('/api/customer/profile', ['name' => 'New'])->assertStatus(401);
    }

    public function test_customer_can_update_profile(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($customer);

        $this->putJson('/api/customer/profile', [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name');
    }
}

