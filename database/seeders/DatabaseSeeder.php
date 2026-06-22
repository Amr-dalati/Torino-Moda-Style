<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@torinomodastyle.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'phone' => null,
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'sales@torinomodastyle.com'],
            [
                'name' => 'Sales Rep Demo',
                'password' => Hash::make('password'),
                'role' => UserRole::Sales,
                'phone' => '+201000000000',
                'is_active' => true,
            ],
        );

        $this->call(DeliverySeeder::class);

        // Rich local demo data (catalog, customers, stock). Not auto-run in testing — tests use phoenix:sync.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
