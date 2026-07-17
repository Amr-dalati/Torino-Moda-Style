<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Services\Products\ProductQueryService;
use App\Services\Stock\VariantAvailabilityService;
use App\Support\ApiResponse;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductQueryService $products,
        protected VariantAvailabilityService $availability,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:newest,price_asc,price_desc,name_asc'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $filters = [
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
        ];
        $page = max(1, (int) $request->integer('page', 1));

        $cacheKey = 'products:list:'.md5(json_encode([
            'filters' => $filters,
            'q' => $validated['q'] ?? null,
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $paginator = CatalogCache::remember($cacheKey, function () use ($validated, $perPage, $filters) {
            if (! empty($validated['q'])) {
                return $this->products->search($validated['q'], $perPage, $filters);
            }

            return $this->products->paginate($perPage, $filters);
        });

        return ApiResponse::paginated($paginator, ProductResource::collection(collect($paginator->items())));
    }

    public function show(int $id): JsonResponse
    {
        $product = CatalogCache::remember("products:show:{$id}", function () use ($id) {
            return $this->products->findOrFail($id);
        });

        $this->availability->attachToProduct($product);

        return ApiResponse::success(new ProductResource($product));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'sort' => ['sometimes', 'string', 'in:newest,price_asc,price_desc,name_asc'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $filters = [
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
        ];

        $paginator = $this->products->search($validated['q'], $perPage, $filters);

        return ApiResponse::paginated($paginator, ProductResource::collection(collect($paginator->items())));
    }

    public function barcode(string $barcode): JsonResponse
    {
        if ($barcode === '' || mb_strlen($barcode) > 100) {
            return ApiResponse::error('Invalid barcode.', 422, ['barcode' => ['Invalid barcode.']]);
        }

        $found = $this->products->findByBarcode($barcode);
        if (! $found) {
            return ApiResponse::error('Product not found.', 404);
        }

        /** @var \App\Models\Product $product */
        $product = $found['product'];

        $product->loadMissing(['category', 'brand', 'variants.color', 'variants.size', 'images', 'primaryImage']);

        $this->availability->attachToProduct($product);

        if ($found['variant']) {
            $this->availability->attachToVariant($found['variant']);
        }

        return ApiResponse::success([
            'product' => new ProductResource($product),
            'variant' => $found['variant'] ? new ProductVariantResource($found['variant']->loadMissing(['color', 'size'])) : null,
        ]);
    }
}
