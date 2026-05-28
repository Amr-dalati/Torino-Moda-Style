<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductQueryService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->withCount('variants')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Product
    {
        return Product::query()
            ->with(['category', 'brand', 'variants.color', 'variants.size'])
            ->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator
     */
    public function search(string $q, int $perPage = 20): LengthAwarePaginator
    {
        $q = trim($q);

        return Product::query()
            ->withCount('variants')
            ->where(function ($builder) use ($q) {
                $builder
                    ->where('product_code', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return array{product: Product, variant: ProductVariant|null}|null
     */
    public function findByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);

        $variant = ProductVariant::query()
            ->with(['product.category', 'product.brand', 'color', 'size'])
            ->where('barcode', $barcode)
            ->first();

        if ($variant) {
            return ['product' => $variant->product, 'variant' => $variant];
        }

        $product = Product::query()
            ->with(['category', 'brand', 'variants.color', 'variants.size'])
            ->where('barcode', $barcode)
            ->first();

        if (! $product) {
            return null;
        }

        return ['product' => $product, 'variant' => null];
    }
}

