<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PhoenixHealthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/phoenix/health', PhoenixHealthController::class);
});
