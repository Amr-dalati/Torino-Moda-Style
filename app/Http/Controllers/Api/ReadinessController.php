<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ops\ReadinessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(ReadinessService $readiness): JsonResponse
    {
        $result = $readiness->assess();

        $httpStatus = match ($result['status']) {
            'ready' => 200,
            'degraded' => 200,
            default => 503,
        };

        return ApiResponse::success([
            'status' => $result['status'],
            'checks' => $result['checks'],
        ], 'OK', $httpStatus);
    }
}
