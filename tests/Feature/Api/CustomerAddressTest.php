<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_endpoints_without_token_return_401(): void
    {
        $this->getJson('/api/customer/addresses')->assertStatus(401);
        $this->postJson('/api/customer/addresses')->assertStatus(401);
    }

    public function test_addresses_require_customer_tokenable(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/customer/addresses')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_create_and_set_default_address(): void
    {
        $this->artisan('db:seed');

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $area = DeliveryArea::query()->firstOrFail();

        $create1 = $this->postJson('/api/customer/addresses', [
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'city' => 'City',
            'area_name' => 'Area Name',
            'is_default' => true,
        ]);

        $create1->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_default', true);

        $create2 = $this->postJson('/api/customer/addresses', [
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 2',
            'is_default' => false,
        ]);

        $create2->assertStatus(201)->assertJsonPath('success', true);

        $list = $this->getJson('/api/customer/addresses');
        $list->assertOk()->assertJsonPath('success', true);

        $id2 = $create2->json('data.id');
        $setDefault = $this->postJson("/api/customer/addresses/{$id2}/default");

        $setDefault->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_default', true);

        $list2 = $this->getJson('/api/customer/addresses');
        $list2->assertOk()->assertJsonPath('success', true);

        $defaults = collect($list2->json('data'))->where('is_default', true)->count();
        $this->assertSame(1, $defaults);
    }

    public function test_customer_cannot_update_another_customers_address(): void
    {
        $this->artisan('db:seed');

        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();

        $area = DeliveryArea::query()->firstOrFail();
        $address = $owner->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Owner address',
            'is_default' => true,
        ]);

        Sanctum::actingAs($other);

        $this->putJson("/api/customer/addresses/{$address->id}", [
            'address_line1' => 'Hacked',
        ])->assertStatus(404);
    }

    public function test_user_token_cannot_access_customer_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/customer/me')->assertStatus(403);
    }
}

