<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\FrontendRegisterController;


Route::get('/test', function () {
    return response()->json(['status' => 'api works']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
});


Route::post('/register', [FrontendRegisterController::class, 'store']);

Route::post('/payment-requests', [PaymentRequestController::class, 'store']);





