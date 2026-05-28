<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PhoenixHealthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);

Route::get('/delivery/regions', [DeliveryController::class, 'regions']);
Route::get('/delivery/areas', [DeliveryController::class, 'areas']);

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

    // Customer authenticated routes (tokenable must be Customer)
    Route::get('/customer/me', [CustomerAuthController::class, 'me']);
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::put('/customer/profile', [CustomerProfileController::class, 'update']);

    Route::get('/customer/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/customer/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/customer/addresses/{id}', [CustomerAddressController::class, 'update'])->whereNumber('id');
    Route::delete('/customer/addresses/{id}', [CustomerAddressController::class, 'destroy'])->whereNumber('id');
    Route::post('/customer/addresses/{id}/default', [CustomerAddressController::class, 'setDefault'])->whereNumber('id');
});
