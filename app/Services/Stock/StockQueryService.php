<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockQueryService
{
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return StockLevel::query()
            ->with(['variant.product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return list<StockLevel>
     */
    public function byProduct(int $productId): array
    {
        Product::query()->findOrFail($productId);

        return StockLevel::query()
            ->with(['variant.product', 'variant.color', 'variant.size', 'warehouse'])
            ->whereHas('variant', fn ($q) => $q->where('product_id', $productId))
            ->orderBy('warehouse_id')
            ->get()
            ->all();
    }

    /**
     * @return list<StockLevel>
     */
    public function byWarehouse(int $warehouseId): array
    {
        Warehouse::query()->findOrFail($warehouseId);

        return StockLevel::query()
            ->with(['warehouse', 'variant.product', 'variant.color', 'variant.size'])
            ->where('warehouse_id', $warehouseId)
            ->orderBy('product_variant_id')
            ->get()
            ->all();
    }
}

