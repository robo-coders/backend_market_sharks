<?php

namespace App\Http\Controllers\Admin;

use App\Events\TeamMarketTrendUpdated;
use App\Http\Controllers\Controller;
use App\Models\MarketTrend;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class MarketTrendController extends Controller
{
    public function show()
    {
        $marketTrend = MarketTrend::latest('id')->first();

        return response()->json($marketTrend);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'gold_trend' => ['sometimes', 'required', 'in:buy,neutral,sell'],
            'dollar_trend' => ['sometimes', 'required', 'in:buy,neutral,sell'],
        ]);

        if (empty($validated)) {
            return response()->json([
                'message' => 'No market trend field was provided.',
            ], 422);
        }

        $marketTrend = MarketTrend::latest('id')->first();

        if (! $marketTrend) {
            $marketTrend = MarketTrend::create([
                'gold_trend' => $validated['gold_trend'] ?? 'neutral',
                'dollar_trend' => $validated['dollar_trend'] ?? 'neutral',
                'updated_by' => auth()->id(),
            ]);
        } else {
            $marketTrend->update([
                ...$validated,
                'updated_by' => auth()->id(),
            ]);
        }

        $freshTrend = $marketTrend->fresh();

        $this->createTeamNotification($freshTrend, array_keys($validated));

        event(new TeamMarketTrendUpdated($freshTrend));

        return response()->json([
            'message' => 'Market trend updated successfully.',
            'data' => $freshTrend,
        ]);
    }

    private function createTeamNotification(MarketTrend $trend, array $changedFields): void
    {
        $parts = [];

        if (in_array('gold_trend', $changedFields, true)) {
            $parts[] = 'Gold: ' . strtoupper($trend->gold_trend);
        }

        if (in_array('dollar_trend', $changedFields, true)) {
            $parts[] = 'Dollar: ' . strtoupper($trend->dollar_trend);
        }

        $message = 'Market trend updated. ' . implode(' · ', $parts) . '.';

        $notification = Notification::create([
            'title' => 'Market trend updated',
            'message' => $message,
            'type' => 'trend',
            'created_by' => auth()->id(),
        ]);

        $teamUserIds = User::role('team')->pluck('id');

        if ($teamUserIds->isNotEmpty()) {
            $notification->users()->syncWithoutDetaching(
                $teamUserIds->mapWithKeys(fn ($id) => [
                    $id => [
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ])->toArray()
            );
        }
    }
}