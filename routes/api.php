<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\Frontend\AuthController;



Route::get('/test', function () {
    return response()->json(['status' => 'api works']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
});


Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/payment-requests', [PaymentRequestController::class, 'store']);





