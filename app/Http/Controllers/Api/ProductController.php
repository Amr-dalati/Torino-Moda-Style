<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Services\Products\ProductQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductQueryService $products,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $paginator = $this->products->paginate($perPage);

        return ApiResponse::paginated($paginator, ProductResource::collection(collect($paginator->items())));
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->products->findOrFail($id);

        return ApiResponse::success(new ProductResource($product));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->products->search($validated['q'], (int) ($validated['per_page'] ?? 20));

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

        $product->loadMissing(['category', 'brand', 'variants.color', 'variants.size']);

        return ApiResponse::success([
            'product' => new ProductResource($product),
            'variant' => $found['variant'] ? new ProductVariantResource($found['variant']->loadMissing(['color', 'size'])) : null,
        ]);
    }
}

