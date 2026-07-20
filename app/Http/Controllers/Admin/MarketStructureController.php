<?php

namespace App\Http\Controllers\Admin;

use App\Events\TeamMarketStructureUpdated;
use App\Http\Controllers\Controller;
use App\Models\MarketStructure;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class MarketStructureController extends Controller
{
    public function show(Request $request)
    {
        $marketStructure = MarketStructure::first();

        if ($request->expectsJson()) {
            return response()->json($marketStructure);
        }

        return back();
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'resistance_1' => ['nullable', 'numeric'],
            'resistance_2' => ['nullable', 'numeric'],
            'resistance_3' => ['nullable', 'numeric'],
            'support_1' => ['nullable', 'numeric'],
            'support_2' => ['nullable', 'numeric'],
            'support_3' => ['nullable', 'numeric'],
        ]);

        $marketStructure = MarketStructure::firstOrCreate(
            [],
            [
                'resistance_1' => null,
                'resistance_2' => null,
                'resistance_3' => null,
                'support_1' => null,
                'support_2' => null,
                'support_3' => null,
            ]
        );

        $marketStructure->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        $freshStructure = $marketStructure->fresh();

        $this->createTeamNotification($freshStructure);

        event(new TeamMarketStructureUpdated($freshStructure));
        app(\App\Services\LevelAlertService::class)->checkAndNotify();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Market structure updated successfully.',
                'data' => $freshStructure,
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'title' => 'Success',
            'text' => 'Market structure updated successfully.',
        ]);
    }

    private function createTeamNotification(MarketStructure $marketStructure): void
    {
        $notification = Notification::create([
            'title' => 'Market structure updated',
            'message' => 'Support and resistance levels were updated for the team dashboard.',
            'type' => 'market_structure',
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