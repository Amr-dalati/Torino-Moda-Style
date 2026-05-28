<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PhoenixHealthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/phoenix/health', PhoenixHealthController::class);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'barcode']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');

    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/stock/product/{product_id}', [StockController::class, 'byProduct'])->whereNumber('product_id');
    Route::get('/stock/warehouse/{warehouse_id}', [StockController::class, 'byWarehouse'])->whereNumber('warehouse_id');
});
