<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerCartController;
use App\Http\Controllers\Api\CustomerCheckoutController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\MockPaymentController;
use App\Http\Controllers\Api\PhoenixHealthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth.strict')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/customer/register', [CustomerAuthController::class, 'register']);
    Route::post('/customer/login', [CustomerAuthController::class, 'login']);
});

Route::get('/delivery/regions', [DeliveryController::class, 'regions']);
Route::get('/delivery/areas', [DeliveryController::class, 'areas']);

Route::middleware(['auth:sanctum', 'tokenable:'.\App\Models\User::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/phoenix/health', PhoenixHealthController::class);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'barcode']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');

    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/stock/product/{product_id}', [StockController::class, 'byProduct'])->whereNumber('product_id');
    Route::get('/stock/warehouse/{warehouse_id}', [StockController::class, 'byWarehouse'])->whereNumber('warehouse_id');
});

Route::middleware(['auth:sanctum', 'tokenable:'.\App\Models\Customer::class])->prefix('customer')->group(function () {
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
    Route::put('/profile', [CustomerProfileController::class, 'update']);

    Route::get('/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/addresses/{id}', [CustomerAddressController::class, 'update'])->whereNumber('id');
    Route::delete('/addresses/{id}', [CustomerAddressController::class, 'destroy'])->whereNumber('id');
    Route::post('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault'])->whereNumber('id');

    Route::get('/cart', [CustomerCartController::class, 'show']);
    Route::middleware('throttle:cart.mutations')->group(function () {
        Route::post('/cart/items', [CustomerCartController::class, 'addItem']);
        Route::put('/cart/items/{id}', [CustomerCartController::class, 'updateItem'])->whereNumber('id');
        Route::delete('/cart/items/{id}', [CustomerCartController::class, 'removeItem'])->whereNumber('id');
        Route::delete('/cart', [CustomerCartController::class, 'clear']);
    });

    Route::middleware('throttle:checkout.strict')->group(function () {
        Route::post('/checkout/quote', [CustomerCheckoutController::class, 'quote']);
        Route::post('/checkout', [CustomerCheckoutController::class, 'checkout']);
    });

    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{id}', [CustomerOrderController::class, 'show'])->whereNumber('id');
    Route::get('/orders/{id}/payment-status', [CustomerOrderController::class, 'paymentStatus'])->whereNumber('id');
});

Route::middleware('local.testing')->group(function () {
    Route::post('/payments/mock/success', [MockPaymentController::class, 'success'])->middleware('throttle:mock.payment.strict');
});
