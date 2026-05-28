<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureLocalOrTesting
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment(['local', 'testing'])) {
            // Hide this endpoint in production.
            return ApiResponse::error('Not found.', 404);
        }

        return $next($request);
    }
}

