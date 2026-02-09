<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentRequestController;

Route::get('/test', function () {
    return response()->json(['status' => 'api works']);
});

Route::post('/payment-requests', [PaymentRequestController::class, 'store']);
