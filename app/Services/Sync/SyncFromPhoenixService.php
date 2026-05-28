<?php

namespace App\Services\Sync;

use App\Integrations\Phoenix\Contracts\PhoenixProductServiceInterface;
use App\Integrations\Phoenix\Contracts\PhoenixStockServiceInterface;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\PhoenixSyncLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SyncFromPhoenixService
{
    public function __construct(
        protected PhoenixProductServiceInterface $products,
        protected PhoenixStockServiceInterface $stock,
    ) {}

    public function syncAll(?int $triggeredByUserId = null): array
    {
        $result = [
            'products' => $this->syncProducts($triggeredByUserId),
            'stock' => $this->syncStock($triggeredByUserId),
        ];

        return $result;
    }

    public function syncProducts(?int $triggeredByUserId = null): array
    {
        $startedAt = now();
        $log = PhoenixSyncLog::query()->create([
            'sync_type' => 'products',
            'direction' => 'inbound',
            'status' => 'running',
            'request_payload' => null,
            'response_payload' => null,
            'started_at' => $startedAt,
            'triggered_by' => $triggeredByUserId,
        ]);

        try {
            $payload = $this->products->fetchAll();
            $syncedAt = now();
            $counts = [
                'categories' => 0,
                'brands' => 0,
                'colors' => 0,
                'sizes' => 0,
                'products' => 0,
                'variants' => 0,
            ];

            DB::transaction(function () use (&$counts, $payload, $syncedAt) {
                foreach ($payload as $p) {
                    $category = null;
                    if (! empty($p['category_code'])) {
                        $category = Category::query()->updateOrCreate(
                            ['code' => (string) $p['category_code']],
                            [
                                'name_en' => (string) ($p['category_code'] ?? null),
                                'is_active' => true,
                                'synced_at' => $syncedAt,
                            ],
                        );
                        $counts['categories']++;
                    }

                    $brand = null;
                    if (! empty($p['brand_code'])) {
                        $brand = Brand::query()->updateOrCreate(
                            ['code' => (string) $p['brand_code']],
                            [
                                'name' => (string) ($p['brand_code'] ?? null),
                                'is_active' => true,
                                'synced_at' => $syncedAt,
                            ],
                        );
                        $counts['brands']++;
                    }

                    $product = Product::query()->updateOrCreate(
                        ['product_code' => (string) $p['product_code']],
                        [
                            'phoenix_id' => $p['id'] ?? null,
                            'barcode' => $p['barcode'] ?? null,
                            'name_ar' => $p['name_ar'] ?? null,
                            'name_en' => $p['name_en'] ?? null,
                            'category_id' => $category?->id,
                            'brand_id' => $brand?->id,
                            'sale_price' => $p['sale_price'] ?? null,
                            'is_active' => (($p['status'] ?? 'active') === 'active'),
                            'synced_at' => $syncedAt,
                        ],
                    );
                    $counts['products']++;

                    foreach (($p['variants'] ?? []) as $v) {
                        $variantPhoenixId = isset($v['id']) ? (string) $v['id'] : '';
                        if ($variantPhoenixId === '') {
                            continue;
                        }

                        $color = null;
                        if (! empty($v['color_code'])) {
                            $color = Color::query()->updateOrCreate(
                                ['code' => (string) $v['color_code']],
                                [
                                    'name_en' => (string) ($v['color_code'] ?? null),
                                    'is_active' => true,
                                    'synced_at' => $syncedAt,
                                ],
                            );
                            $counts['colors']++;
                        }

                        $size = null;
                        if (! empty($v['size_code'])) {
                            $size = Size::query()->updateOrCreate(
                                ['code' => (string) $v['size_code']],
                                [
                                    'name' => (string) ($v['size_code'] ?? null),
                                    'is_active' => true,
                                    'synced_at' => $syncedAt,
                                ],
                            );
                            $counts['sizes']++;
                        }

                        ProductVariant::query()->updateOrCreate(
                            ['phoenix_id' => $variantPhoenixId],
                            [
                                'product_id' => $product->id,
                                'sku' => $v['sku'] ?? null,
                                'barcode' => $v['barcode'] ?? null,
                                'color_id' => $color?->id,
                                'size_id' => $size?->id,
                                'sale_price' => $v['sale_price'] ?? $product->sale_price,
                                'is_active' => true,
                                'synced_at' => $syncedAt,
                            ],
                        );
                        $counts['variants']++;
                    }
                }
            });

            $log->update([
                'status' => 'success',
                'response_payload' => [
                    'counts' => $counts,
                    'synced_at' => $syncedAt->toIso8601String(),
                ],
                'finished_at' => now(),
            ]);

            return $counts;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    public function syncStock(?int $triggeredByUserId = null): array
    {
        $startedAt = now();
        $log = PhoenixSyncLog::query()->create([
            'sync_type' => 'stock',
            'direction' => 'inbound',
            'status' => 'running',
            'request_payload' => null,
            'response_payload' => null,
            'started_at' => $startedAt,
            'triggered_by' => $triggeredByUserId,
        ]);

        try {
            $payload = $this->stock->fetchAll();
            $syncedAt = now();
            $counts = [
                'warehouses' => 0,
                'stock_levels' => 0,
                'skipped_rows' => 0,
            ];

            DB::transaction(function () use (&$counts, $payload, $syncedAt) {
                foreach ($payload as $row) {
                    $variantPhoenixId = (string) ($row['variant_id'] ?? '');
                    $warehouseCode = (string) ($row['warehouse_code'] ?? '');

                    if ($variantPhoenixId === '' || $warehouseCode === '') {
                        $counts['skipped_rows']++;
                        continue;
                    }

                    $variant = ProductVariant::query()->where('phoenix_id', $variantPhoenixId)->first();
                    if (! $variant) {
                        $counts['skipped_rows']++;
                        continue;
                    }

                    $warehouse = Warehouse::query()->updateOrCreate(
                        ['code' => $warehouseCode],
                        [
                            'name' => $warehouseCode,
                            'is_active' => true,
                            'synced_at' => $syncedAt,
                        ],
                    );
                    $counts['warehouses']++;

                    StockLevel::query()->updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity_on_hand' => (float) ($row['quantity'] ?? 0),
                            'synced_at' => $syncedAt,
                        ],
                    );
                    $counts['stock_levels']++;
                }
            });

            $log->update([
                'status' => 'success',
                'response_payload' => [
                    'counts' => $counts,
                    'synced_at' => $syncedAt->toIso8601String(),
                ],
                'finished_at' => now(),
            ]);

            return $counts;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }
}

