<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\Frontend\AuthController;


Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::post('/payment-requests', [PaymentRequestController::class, 'store'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
});