<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class VariantAvailabilityService
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, bool>
     */
    public function inStockMap(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));

        if ($variantIds === []) {
            return [];
        }

        $rows = DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->whereIn('stock_levels.product_variant_id', $variantIds)
            ->where('warehouses.is_active', true)
            ->groupBy('stock_levels.product_variant_id')
            ->selectRaw('stock_levels.product_variant_id as variant_id')
            ->selectRaw('SUM(stock_levels.quantity_on_hand - stock_levels.quantity_reserved) as available')
            ->get();

        $map = array_fill_keys($variantIds, false);

        foreach ($rows as $row) {
            $map[(int) $row->variant_id] = (float) $row->available > 0;
        }

        return $map;
    }

    public function attachToProduct(Product $product): void
    {
        if (! $product->relationLoaded('variants')) {
            return;
        }

        $map = $this->inStockMap($product->variants->pluck('id')->all());

        foreach ($product->variants as $variant) {
            $variant->setAttribute('is_in_stock', $map[$variant->id] ?? false);
        }
    }

    public function attachToVariant(ProductVariant $variant): void
    {
        $map = $this->inStockMap([$variant->id]);
        $variant->setAttribute('is_in_stock', $map[$variant->id] ?? false);
    }
}
