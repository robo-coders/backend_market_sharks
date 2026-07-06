<?php

namespace App\Http\Controllers;

use App\Services\GoldPriceService;
use Illuminate\Http\JsonResponse;

class GoldPriceController extends Controller
{
    public function __construct(protected GoldPriceService $goldPriceService)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->goldPriceService->getPrice());
    }
}