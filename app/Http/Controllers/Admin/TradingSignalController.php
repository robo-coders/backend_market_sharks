<?php

namespace App\Http\Controllers\Admin;

use App\Events\TeamSignalUpdated;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\TradingSignal;
use App\Models\User;
use App\Services\GoldPriceService;
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

    public function store(Request $request, GoldPriceService $goldPriceService)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:20'],
            'signal_type' => ['required', 'in:buy,sell'],
            'entry_price' => ['required', 'numeric'],
            'stop_loss' => ['required', 'numeric'],
            'take_profit' => ['required', 'numeric'],
            'gold_price_at_entry' => ['nullable', 'numeric'],
            'opened_at' => ['nullable', 'date'],
        ]);

        // Only one live signal at a time — pending OR open both count.
        if (TradingSignal::whereIn('status', ['pending', 'open'])->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A signal is already active. Close it before posting a new one.',
                ], 422);
            }

            return back()->with('status', [
                'type' => 'error',
                'title' => 'Signal already active',
                'text' => 'Close the current active signal before posting a new one.',
            ]);
        }

        // Decide the initial state from the live price. If the market is
        // already at/above entry, the trade is live immediately (open);
        // otherwise it waits as pending until the monitor sees entry hit.
        $priceData = $goldPriceService->getPrice();
        $livePrice = (float) ($priceData['price'] ?? 0);
        $entry = (float) $validated['entry_price'];

        $isLiveNow = $livePrice > 0 && $livePrice >= $entry;

        $signal = TradingSignal::create([
            ...$validated,
            'status' => $isLiveNow ? 'open' : 'pending',
            'opened_at' => $validated['opened_at'] ?? now(),
            'activated_at' => $isLiveNow ? now() : null,
            'gold_price_at_entry' => $validated['gold_price_at_entry']
                ?? ($livePrice > 0 ? $livePrice : null),
        ]);

        $freshSignal = $signal->fresh();

        $this->createTeamNotification($freshSignal, $isLiveNow ? 'activated' : 'placed');

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
            'opened_at' => ['sometimes', 'date'],
        ]);

        // Entry is locked once the trade is live (open). It's the value
        // every P/L calc and the trade log are measured against — editing
        // it after activation would silently rewrite trade history.
        if ($signal->status === 'open') {
            unset($validated['entry_price'], $validated['signal_type']);
        }

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

        if (in_array($signal->status, ['closed', 'cancelled'], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Trading signal is already closed.',
                ], 422);
            }

            return back()->with('error', 'Trading signal is already closed.');
        }

        // Remember whether this was a pending signal BEFORE closing, so
        // we can word the response correctly (cancel vs close).
        $wasPending = $signal->status === 'pending';

        try {
            $tradeLog = $signalCloseService->closeManually($signal, auth()->id());
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $successText = $wasPending
            ? 'Signal cancelled — it never reached entry, so nothing was logged.'
            : 'Trading signal closed successfully.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $successText,
                'data' => [
                    'signal' => $signal->fresh(),
                    'trade_log' => $tradeLog, // null for a cancel
                ],
            ]);
        }

        return back()->with('success', $successText);
    }

    private function createTeamNotification(
        TradingSignal $signal,
        string $action,
        ?string $closeReason = null
    ): void {
        $title = match ($action) {
            'placed' => 'Signal placed',
            'activated' => 'Trade activated',
            'updated' => 'Trading signal updated',
            'closed' => 'Trading signal closed',
            'cancelled' => 'Signal cancelled',
            default => 'Trading signal activity',
        };

        $symbol = strtoupper($signal->symbol);
        $type = strtoupper($signal->signal_type);

        $message = match ($action) {
            // Pending: waiting for price to reach entry. Deliberately does
            // NOT say "opened at" — no trade exists yet.
            'placed' => "{$symbol} {$type} signal placed — waiting for entry {$signal->entry_price}.",
            // Live from the moment of creation (market already at/above entry).
            'activated' => "{$symbol} {$type} trade is now live at entry {$signal->entry_price}.",
            'updated' => "{$symbol} signal was updated. Current status: " . strtoupper($signal->status) . '.',
            'closed' => "{$symbol} signal was closed" . ($closeReason ? " ({$closeReason})" : '') . '.',
            'cancelled' => "{$symbol} {$type} signal cancelled — price never reached entry {$signal->entry_price}.",
            default => "{$symbol} signal activity recorded.",
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