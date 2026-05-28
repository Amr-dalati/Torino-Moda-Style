<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_regions_and_areas(): void
    {
        $this->artisan('db:seed');

        $regions = $this->getJson('/api/delivery/regions');
        $regions->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $areas = $this->getJson('/api/delivery/areas');
        $areas->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_areas_filter_by_region_id(): void
    {
        $this->artisan('db:seed');

        $regionA = DeliveryRegion::query()->where('code', 'REGION_A')->firstOrFail();

        $areas = $this->getJson("/api/delivery/areas?region_id={$regionA->id}");
        $areas->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }
}

