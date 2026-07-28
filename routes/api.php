<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\Frontend\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::post('/payment-requests', [PaymentRequestController::class, 'store'])->middleware('throttle:5,1');

Route::post('/price', function (Request $request) {
    if ($request->header('X-API-KEY') !== env('PRICE_API_KEY')) {
        return response()->json(['error' => 'unauthorized'], 401);
    }

    $price = round((float) $request->input('price'), 2);
    if ($price <= 0) {
        return response()->json(['error' => 'bad price'], 422);
    }

    Cache::put('gold_live_price', [
        'price'      => $price,
        'updated_at' => now()->toIso8601String(),
        'source'     => 'mt5',
        'stale'      => false,
    ], 30);

    return response()->json(['ok' => true]);
})->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
});