<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Services\Catalog\BrandQueryService;
use App\Services\Catalog\CategoryQueryService;
use App\Support\ApiResponse;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __construct(
        protected CategoryQueryService $categories,
        protected BrandQueryService $brands,
    ) {}

    public function categories(): JsonResponse
    {
        $data = CatalogCache::remember('categories:list', function () {
            return CategoryResource::collection($this->categories->listActive())->resolve();
        });

        return ApiResponse::success($data);
    }

    public function brands(): JsonResponse
    {
        $data = CatalogCache::remember('brands:list', function () {
            return BrandResource::collection($this->brands->listActive())->resolve();
        });

        return ApiResponse::success($data);
    }
}
