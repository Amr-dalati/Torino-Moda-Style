<?php

namespace Tests\Feature\Seeders;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_seeder_skips_outside_staging(): void
    {
        $this->seed(\Database\Seeders\StagingSeeder::class);

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_staging_seeder_creates_uat_data_in_staging(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'staging';

        putenv('STAGING_CUSTOMER_PHONE=+96890009999');
        putenv('STAGING_CUSTOMER_PASSWORD=staging-test-password');
        putenv('STAGING_ADMIN_PASSWORD=staging-admin-password');

        try {
            $this->seed(\Database\Seeders\StagingSeeder::class);

            $this->assertDatabaseHas('customers', ['phone' => '+96890009999']);
            $this->assertTrue(
                Product::query()->where('product_code', 'STAGING-UAT-PRODUCT')->exists()
            );
        } finally {
            $this->app['env'] = $originalEnv;
            putenv('STAGING_CUSTOMER_PHONE');
            putenv('STAGING_CUSTOMER_PASSWORD');
            putenv('STAGING_ADMIN_PASSWORD');
        }
    }
}
