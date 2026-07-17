<?php

use App\Http\Controllers\LegalController;
use App\Http\Controllers\ThawaniReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Thawani hosted checkout browser return targets.
| No payment state changes — redirects to the mobile app for status polling.
| Configure THAWANI_SUCCESS_URL / THAWANI_CANCEL_URL to these routes.
*/
Route::get('/payments/thawani/success', [ThawaniReturnController::class, 'success']);
Route::get('/payments/thawani/cancel', [ThawaniReturnController::class, 'cancel']);

Route::prefix('legal')->group(function () {
    Route::get('/privacy', [LegalController::class, 'privacy']);
    Route::get('/terms', [LegalController::class, 'terms']);
    Route::get('/returns', [LegalController::class, 'returns']);
    Route::get('/shipping', [LegalController::class, 'shipping']);
    Route::get('/contact', [LegalController::class, 'contact']);
    Route::get('/account-deletion', [LegalController::class, 'accountDeletion']);
});
