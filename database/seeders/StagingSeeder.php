<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\DeliveryRegion;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Staging-only UAT seed data. Never auto-runs in production.
 *
 * Credentials are supplied via environment variables — never logged.
 *
 * Required for customer login:
 * - STAGING_CUSTOMER_PHONE
 * - STAGING_CUSTOMER_PASSWORD
 *
 * Optional admin override:
 * - STAGING_ADMIN_EMAIL (default: admin@torinomodastyle.com)
 * - STAGING_ADMIN_PASSWORD
 */
class StagingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('staging')) {
            $this->command?->warn('StagingSeeder skipped: only allowed when APP_ENV=staging.');

            return;
        }

        $customerPhone = trim((string) env('STAGING_CUSTOMER_PHONE', ''));
        $customerPassword = trim((string) env('STAGING_CUSTOMER_PASSWORD', ''));

        if ($customerPhone === '' || $customerPassword === '') {
            $this->command?->error('StagingSeeder requires STAGING_CUSTOMER_PHONE and STAGING_CUSTOMER_PASSWORD.');

            return;
        }

        $adminEmail = trim((string) env('STAGING_ADMIN_EMAIL', 'admin@torinomodastyle.com'));
        $adminPassword = trim((string) env('STAGING_ADMIN_PASSWORD', env('STAGING_CUSTOMER_PASSWORD', '')));

        if ($adminPassword === '') {
            $this->command?->error('StagingSeeder requires STAGING_ADMIN_PASSWORD or STAGING_CUSTOMER_PASSWORD.');

            return;
        }

        DB::transaction(function () use ($customerPhone, $customerPassword, $adminEmail, $adminPassword) {
            User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Staging Admin',
                    'password' => Hash::make($adminPassword),
                    'role' => UserRole::Admin,
                    'phone' => null,
                    'is_active' => true,
                ],
            );

            Customer::query()->updateOrCreate(
                ['phone' => $customerPhone],
                [
                    'name' => 'Staging UAT Customer',
                    'email' => 'staging-customer@torinomodastyle.local',
                    'password' => Hash::make($customerPassword),
                    'is_active' => true,
                ],
            );

            $region = DeliveryRegion::query()->updateOrCreate(
                ['code' => 'STAGING_OMAN'],
                [
                    'name_en' => 'Oman (Staging)',
                    'name_ar' => 'عُمان (تجريبي)',
                    'is_active' => true,
                ],
            );

            DeliveryArea::query()->updateOrCreate(
                ['code' => 'STAGING_MUSCAT'],
                [
                    'delivery_region_id' => $region->id,
                    'name_en' => 'Muscat (Staging)',
                    'name_ar' => 'مسقط (تجريبي)',
                    'delivery_fee' => 2.00,
                    'is_active' => true,
                ],
            );

            $warehouse = Warehouse::query()->updateOrCreate(
                ['code' => 'STAGING_WH'],
                ['name' => 'Staging Warehouse', 'is_active' => true],
            );

            $category = Category::query()->updateOrCreate(
                ['code' => 'STAGING_CAT'],
                ['name_en' => 'Staging Category', 'name_ar' => 'فئة تجريبية', 'is_active' => true],
            );

            $brand = Brand::query()->updateOrCreate(
                ['code' => 'STAGING_BRAND'],
                ['name' => 'Torino Moda Style', 'is_active' => true],
            );

            $colorIn = Color::query()->updateOrCreate(
                ['code' => 'STAGING_BLACK'],
                ['name_en' => 'Black', 'name_ar' => 'أسود', 'is_active' => true],
            );

            $colorOut = Color::query()->updateOrCreate(
                ['code' => 'STAGING_WHITE'],
                ['name_en' => 'White', 'name_ar' => 'أبيض', 'is_active' => true],
            );

            $size = Size::query()->updateOrCreate(
                ['code' => 'STAGING_38'],
                ['name' => '38', 'sort_order' => 38, 'is_active' => true],
            );

            $product = Product::query()->updateOrCreate(
                ['product_code' => 'STAGING-UAT-PRODUCT'],
                [
                    'barcode' => 'STAGINGUAT001',
                    'name_en' => 'Staging UAT Product',
                    'name_ar' => 'منتج تجريبي للاختبار',
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'sale_price' => 25.00,
                    'is_active' => true,
                ],
            );

            $inStockVariant = ProductVariant::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'color_id' => $colorIn->id,
                    'size_id' => $size->id,
                ],
                [
                    'sku' => 'STAGING-UAT-PRODUCT-BLACK-38',
                    'barcode' => 'STAGINGUATV001',
                    'sale_price' => 25.00,
                    'is_active' => true,
                ],
            );

            $outOfStockVariant = ProductVariant::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'color_id' => $colorOut->id,
                    'size_id' => $size->id,
                ],
                [
                    'sku' => 'STAGING-UAT-PRODUCT-WHITE-38',
                    'barcode' => 'STAGINGUATV002',
                    'sale_price' => 25.00,
                    'is_active' => true,
                ],
            );

            StockLevel::query()->updateOrCreate(
                [
                    'product_variant_id' => $inStockVariant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity_on_hand' => 10,
                    'quantity_reserved' => 0,
                ],
            );

            StockLevel::query()->updateOrCreate(
                [
                    'product_variant_id' => $outOfStockVariant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ],
            );
        });

        $this->command?->info('StagingSeeder completed.');
        $this->command?->line('Customer phone: configured via STAGING_CUSTOMER_PHONE');
        $this->command?->line('Admin email: '.$adminEmail);
        $this->command?->line('Product code: STAGING-UAT-PRODUCT (in-stock + out-of-stock variants)');
    }
}
