<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Services\Stock\StockQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        protected StockQueryService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min(200, $perPage));

        $paginator = $this->stock->paginate($perPage);

        return ApiResponse::paginated($paginator, StockLevelResource::collection(collect($paginator->items())));
    }

    public function byProduct(int $productId): JsonResponse
    {
        $rows = $this->stock->byProduct($productId);

        return ApiResponse::success(StockLevelResource::collection(collect($rows)));
    }

    public function byWarehouse(int $warehouseId): JsonResponse
    {
        $rows = $this->stock->byWarehouse($warehouseId);

        return ApiResponse::success(StockLevelResource::collection(collect($rows)));
    }
}

