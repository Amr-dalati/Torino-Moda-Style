<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\DeliveryRegion;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        // Generic sample data (replace freely per business needs)
        $regionA = DeliveryRegion::query()->updateOrCreate(
            ['code' => 'REGION_A'],
            ['name_en' => 'Region A', 'name_ar' => 'المنطقة A', 'is_active' => true],
        );

        $regionB = DeliveryRegion::query()->updateOrCreate(
            ['code' => 'REGION_B'],
            ['name_en' => 'Region B', 'name_ar' => 'المنطقة B', 'is_active' => true],
        );

        DeliveryArea::query()->updateOrCreate(
            ['code' => 'AREA_A1'],
            [
                'delivery_region_id' => $regionA->id,
                'name_en' => 'Area A1',
                'name_ar' => 'منطقة A1',
                'delivery_fee' => 50,
                'is_active' => true,
            ],
        );

        DeliveryArea::query()->updateOrCreate(
            ['code' => 'AREA_A2'],
            [
                'delivery_region_id' => $regionA->id,
                'name_en' => 'Area A2',
                'name_ar' => 'منطقة A2',
                'delivery_fee' => 70,
                'is_active' => true,
            ],
        );

        DeliveryArea::query()->updateOrCreate(
            ['code' => 'AREA_B1'],
            [
                'delivery_region_id' => $regionB->id,
                'name_en' => 'Area B1',
                'name_ar' => 'منطقة B1',
                'delivery_fee' => 90,
                'is_active' => true,
            ],
        );
    }
}

