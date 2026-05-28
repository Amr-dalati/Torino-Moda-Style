<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureTokenableIs
{
    /**
     * @param  class-string  $expectedClass
     */
    public function handle(Request $request, Closure $next, string $expectedClass)
    {
        $tokenable = $request->user();

        if (! $tokenable instanceof $expectedClass) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return $next($request);
    }
}

