<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductQueryService
{
    /**
     * @param  array{
     *   category_id?: int|null,
     *   brand_id?: int|null,
     *   q?: string|null,
     *   sort?: string|null,
     * }  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseStorefrontQuery($filters);

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): Product
    {
        return Product::query()
            ->storefrontVisible()
            ->with([
                'category',
                'brand',
                'variants.color',
                'variants.size',
                'images',
                'primaryImage',
            ])
            ->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator
     */
    public function search(string $q, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $q = trim($q);
        $filters['q'] = $q;

        $query = $this->baseStorefrontQuery($filters)
            ->where(function ($builder) use ($q) {
                $builder
                    ->where('product_code', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%");
            });

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate($perPage);
    }

    /**
     * @return array{product: Product, variant: ProductVariant|null}|null
     */
    public function findByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);

        $variant = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->storefrontVisible())
            ->with(['product.category', 'product.brand', 'product.images', 'product.primaryImage', 'color', 'size'])
            ->where('barcode', $barcode)
            ->first();

        if ($variant) {
            $variant->product->loadMissing(['variants.color', 'variants.size']);

            return ['product' => $variant->product, 'variant' => $variant];
        }

        $product = Product::query()
            ->storefrontVisible()
            ->with(['category', 'brand', 'variants.color', 'variants.size', 'images', 'primaryImage'])
            ->where('barcode', $barcode)
            ->first();

        if (! $product) {
            return null;
        }

        return ['product' => $product, 'variant' => null];
    }

    /**
     * @param  array{
     *   category_id?: int|null,
     *   brand_id?: int|null,
     *   q?: string|null,
     *   sort?: string|null,
     * }  $filters
     */
    protected function baseStorefrontQuery(array $filters = []): Builder
    {
        $query = Product::query()
            ->storefrontVisible()
            ->withCount('variants')
            ->with(['category', 'brand', 'primaryImage']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        return $query;
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('sale_price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('sale_price')->orderByDesc('id'),
            'name_asc' => $query->orderBy('name_en')->orderByDesc('id'),
            default => $query->orderByDesc('sort_order')->orderByDesc('id'),
        };
    }
}
