<?php

namespace App\Http\Controllers\Admin;

use App\Events\TeamSignalUpdated;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\TradingSignal;
use App\Models\User;
use App\Services\SignalCloseService;
use Illuminate\Http\Request;

class TradingSignalController extends Controller
{
    public function index(Request $request)
    {
        $signals = TradingSignal::latest()->get();

        if ($request->expectsJson()) {
            return response()->json($signals);
        }

        return back();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:20'],
            'signal_type' => ['required', 'in:buy,sell'],
            'entry_price' => ['required', 'numeric'],
            'stop_loss' => ['required', 'numeric'],
            'take_profit' => ['required', 'numeric'],
            'gold_price_at_entry' => ['nullable', 'numeric'],
            'status' => ['nullable', 'in:open,closed'],
            'opened_at' => ['nullable', 'date'],
        ]);

        if (TradingSignal::where('status', 'open')->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A signal is already open. Close it before posting a new one.',
                ], 422);
            }

            return back()->with('status', [
                'type' => 'error',
                'title' => 'Signal already open',
                'text' => 'Close the current active signal before posting a new one.',
            ]);
        }

        $signal = TradingSignal::create([
            ...$validated,
            'status' => $validated['status'] ?? 'open',
            'opened_at' => $validated['opened_at'] ?? now(),
        ]);

        $freshSignal = $signal->fresh();

        $this->createTeamNotification($freshSignal, 'created');

        event(new TeamSignalUpdated($freshSignal));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Trading signal created successfully.',
                'data' => $freshSignal,
            ], 201);
        }

        return back()->with('status', [
            'type' => 'success',
            'title' => 'Success',
            'text' => 'Trading signal saved successfully.',
        ]);
    }

    public function show(Request $request, $id)
    {
        $signal = TradingSignal::findOrFail($id);

        if ($request->expectsJson()) {
            return response()->json($signal);
        }

        return back();
    }

    public function update(Request $request, $id)
    {
        $signal = TradingSignal::findOrFail($id);

        $validated = $request->validate([
            'symbol' => ['sometimes', 'string', 'max:20'],
            'signal_type' => ['sometimes', 'in:buy,sell'],
            'entry_price' => ['sometimes', 'numeric'],
            'stop_loss' => ['sometimes', 'numeric'],
            'take_profit' => ['sometimes', 'numeric'],
            'gold_price_at_entry' => ['nullable', 'numeric'],
            'status' => ['sometimes', 'in:open,closed'],
            'opened_at' => ['sometimes', 'date'],
            'closed_at' => ['nullable', 'date'],
        ]);

        $signal->update($validated);

        $freshSignal = $signal->fresh();

        $this->createTeamNotification($freshSignal, 'updated');

        event(new TeamSignalUpdated($freshSignal));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Trading signal updated successfully.',
                'data' => $freshSignal,
            ]);
        }

        return back()->with('success', 'Trading signal updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $signal = TradingSignal::findOrFail($id);
        $signal->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Trading signal deleted successfully.',
            ]);
        }

        return back()->with('success', 'Trading signal deleted successfully.');
    }

    public function close(Request $request, $id, SignalCloseService $signalCloseService)
    {
        $signal = TradingSignal::findOrFail($id);

        if ($signal->status === 'closed') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Trading signal is already closed.',
                ], 422);
            }

            return back()->with('error', 'Trading signal is already closed.');
        }

        try {
            $tradeLog = $signalCloseService->closeManually($signal, auth()->id());
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Trading signal closed successfully.',
                'data' => [
                    'signal' => $signal->fresh(),
                    'trade_log' => $tradeLog,
                ],
            ]);
        }

        return back()->with('success', 'Trading signal closed successfully.');
    }

    private function createTeamNotification(
        TradingSignal $signal,
        string $action,
        ?string $closeReason = null
    ): void {
        $title = match ($action) {
            'created' => 'New trading signal',
            'updated' => 'Trading signal updated',
            'closed' => 'Trading signal closed',
            default => 'Trading signal activity',
        };

        $message = match ($action) {
            'created' => strtoupper($signal->symbol) . ' ' . strtoupper($signal->signal_type) . ' signal opened at ' . $signal->entry_price . '.',
            'updated' => strtoupper($signal->symbol) . ' signal was updated. Current status: ' . strtoupper($signal->status) . '.',
            'closed' => strtoupper($signal->symbol) . ' signal was closed'
                . ($closeReason ? ' (' . $closeReason . ')' : '')
                . '.',
            default => strtoupper($signal->symbol) . ' signal activity recorded.',
        };

        $notification = Notification::create([
            'title' => $title,
            'message' => $message,
            'type' => 'signal',
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