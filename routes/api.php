<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\Frontend\AuthController;
use App\Http\Controllers\Admin\MarketStructureController;
use App\Http\Controllers\Admin\MarketTrendController;
use App\Http\Controllers\Admin\TradeLogController;
use App\Http\Controllers\Admin\TradingSignalController;

Route::get('/test', function () {
    return response()->json(['status' => 'api works']);
});

Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/payment-requests', [PaymentRequestController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::prefix('admin')->group(function () {
        Route::get('/market-structure', [MarketStructureController::class, 'show']);
        Route::put('/market-structure', [MarketStructureController::class, 'update']);

        Route::get('/market-trend', [MarketTrendController::class, 'show']);
        Route::put('/market-trend', [MarketTrendController::class, 'update']);

        Route::get('/trading-signals', [TradingSignalController::class, 'index']);
        Route::post('/trading-signals', [TradingSignalController::class, 'store']);
        Route::post('/trading-signals/{id}/close', [TradingSignalController::class, 'close']);
        Route::get('/trading-signals/{id}', [TradingSignalController::class, 'show']);
        Route::put('/trading-signals/{id}', [TradingSignalController::class, 'update']);
        Route::delete('/trading-signals/{id}', [TradingSignalController::class, 'destroy']);

        Route::get('/trade-logs', [TradeLogController::class, 'index']);
        Route::get('/trade-logs/{id}', [TradeLogController::class, 'show']);
    });
});