<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhoenixHealthController extends Controller
{
    public function __invoke(PhoenixClientInterface $client): JsonResponse
    {
        return ApiResponse::success([
            'phoenix_reachable' => $client->isHealthy(),
            'use_mock' => (bool) config('phoenix.use_mock'),
        ]);
    }
}
