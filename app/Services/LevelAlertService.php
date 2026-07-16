<?php

namespace App\Services;

use App\Events\LevelProximityAlert;
use App\Models\MarketStructure;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LevelAlertService
{
    private const MARGIN = 1.0;

    private const CACHE_KEY = 'levels-monitor:in-range';

    public function __construct(protected GoldPriceService $goldPriceService)
    {
    }

    public function checkAndNotify(): void
    {
        $structure = MarketStructure::latest('id')->first();

        if (!$structure) {
            return; // no levels defined yet, don't spend an API call
        }

        $priceData = $this->goldPriceService->getPrice();
        $price = (float) ($priceData['price'] ?? 0);

        if ($price <= 0) {
            return; // provider unavailable, skip this tick
        }

        $levels = [
            'S1' => $structure->support_1,
            'S2' => $structure->support_2,
            'S3' => $structure->support_3,
            'R1' => $structure->resistance_1,
            'R2' => $structure->resistance_2,
            'R3' => $structure->resistance_3,
        ];

        $previouslyInRange = Cache::get(self::CACHE_KEY, []);
        $inRange = [];
        $newlyInRange = [];

        foreach ($levels as $label => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }

            $value = (float) str_replace(',', '', (string) $raw);

            if ($value <= 0) {
                continue;
            }

            if (abs($price - $value) <= self::MARGIN) {
                // Keyed by label AND value: if an admin moves S2, the key
                // changes and the alert re-arms even if price never left.
                $key = "{$label}:{$value}";
                $inRange[] = $key;

                if (!in_array($key, $previouslyInRange, true)) {
                    $newlyInRange[] = ['label' => $label, 'value' => $value];
                }
            }
        }

        Cache::put(self::CACHE_KEY, $inRange, now()->addDay());

        if (empty($newlyInRange)) {
            return;
        }

        $this->alert($price, $newlyInRange);
    }

    protected function alert(float $price, array $levels): void
    {
        $detail = implode(' · ', array_map(
            fn ($l) => sprintf('%s %s', $l['label'], number_format($l['value'], 2)),
            $levels
        ));
        try {
            $notification = Notification::create([
                'title' => 'Price near key level',
                'message' => 'Gold ' . number_format($price, 2) . ' near ' . $detail . '.',
                'type' => 'level',
                'created_by' => null, // system-generated from the monitor loop, no acting user
            ]);

            $teamUserIds = User::role('team')->pluck('id');

            if ($teamUserIds->isNotEmpty()) {
                $notification->users()->syncWithoutDetaching(
                    $teamUserIds->mapWithKeys(fn ($id) => [
                        $id => ['read_at' => null, 'created_at' => now(), 'updated_at' => now()],
                    ])->toArray()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Level-proximity notification failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // 2) Real-time broadcast (Pusher, team.dashboard channel) so open
        //    dashboards get an instant toast + beep.
        try {
            event(new LevelProximityAlert($price, $levels));
        } catch (\Throwable $e) {
            Log::warning('Level-proximity broadcast failed', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Level proximity alert fired', [
            'price' => $price,
            'levels' => $levels,
        ]);
    }
}