<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryAreaResource;
use App\Http\Resources\DeliveryRegionResource;
use App\Services\Delivery\DeliveryQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryQueryService $delivery,
    ) {}

    public function regions(): JsonResponse
    {
        $regions = $this->delivery->regions();

        return ApiResponse::success(DeliveryRegionResource::collection(collect($regions)));
    }

    public function areas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_id' => ['sometimes', 'integer', 'exists:delivery_regions,id'],
        ]);

        $areas = $this->delivery->areas(isset($validated['region_id']) ? (int) $validated['region_id'] : null);

        return ApiResponse::success(DeliveryAreaResource::collection(collect($areas)));
    }
}

