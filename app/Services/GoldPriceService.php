<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoldPriceService
{
    protected const CACHE_KEY = 'gold_live_price';

    // How long a fetched price is considered fresh before we hit the
    // provider again. Both the admin and team dashboards read from this
    // same cache, so the provider is only called once per window,
    // no matter how many people are watching the dashboard.
    protected const CACHE_TTL_SECONDS = 30;

    public function getPrice(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->fetchFromProvider();
        });
    }

    protected function fetchFromProvider(): array
    {
        try {
            $response = Http::timeout(5)->get('https://api.gold-api.com/price/XAU');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'price' => round((float) ($data['price'] ?? 0), 2),
                    'updated_at' => now()->toIso8601String(),
                    'source' => 'gold-api.com',
                    'stale' => false,
                ];
            }

            Log::warning('Gold price provider returned a non-2xx response.', [
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gold price fetch failed: ' . $e->getMessage());
        }

        // Provider is down / erroring: serve the last known good price
        // (if any) instead of breaking the dashboard, but flag it as stale
        // so the frontend can show a "feed offline" state if it wants to.
        $lastKnown = Cache::get(self::CACHE_KEY);

        if ($lastKnown) {
            return array_merge($lastKnown, ['stale' => true]);
        }

        return [
            'price' => 0,
            'updated_at' => now()->toIso8601String(),
            'source' => 'unavailable',
            'stale' => true,
        ];
    }
}