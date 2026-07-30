<?php

namespace App\Services;

use App\Events\TeamSignalUpdated;
use App\Models\Notification;
use App\Models\TradeLog;
use App\Models\TradingSignal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SignalCloseService
{
    public function __construct(protected GoldPriceService $goldPriceService)
    {
    }

    /**
     * Called each monitor tick. Promotes a pending signal to a live
     * trade (open) once the live price reaches its entry. This is what
     * turns "Active signal" into "Active trade" on the dashboards.
     */
    public function activatePendingIfReached(): void
    {
        DB::transaction(function () {
            $signal = TradingSignal::where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$signal) {
                return;
            }

            $priceData = $this->goldPriceService->getPrice();
            $price = (float) ($priceData['price'] ?? 0);

            if ($price <= 0) {
                return; // provider unavailable, skip this tick
            }

            if (!$signal->hasReachedEntry($price)) {
                return; // still waiting for price to reach entry
            }

            $signal->update([
                'status' => 'open',
                'activated_at' => now(),
                // Stamp the real price at the moment the trade went live,
                // so P/L context and the log reflect activation, not placement.
                'gold_price_at_entry' => $signal->gold_price_at_entry ?? $price,
            ]);

            $fresh = $signal->fresh();

            $this->notifyActivated($fresh);

            // justActivated = true → dashboard shows the "trade live" toast
            // only on this pending→open transition, not on every update.
            event(new TeamSignalUpdated($fresh, null, true));

            Log::info('Trading signal activated', [
                'signal_id' => $fresh->id,
                'entry_price' => $fresh->entry_price,
                'activated_price' => $price,
            ]);
        });
    }

    /**
     * Called by the scheduled command. Checks the single OPEN (live)
     * signal against the live price and closes it if TP or SL was hit.
     * Pending signals are never auto-closed here — they only get
     * promoted to open by activatePendingIfReached().
     */
    public function checkAndCloseIfTriggered(): void
    {
        DB::transaction(function () {
            $signal = TradingSignal::where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$signal) {
                return;
            }

            $priceData = $this->goldPriceService->getPrice();
            $price = (float) ($priceData['price'] ?? 0);

            if ($price <= 0) {
                return; // provider unavailable, skip this tick
            }

            $reason = $this->resolveTrigger($signal, $price);

            if ($reason === null) {
                return; // neither TP nor SL hit yet
            }

            $this->closeSignal($signal, $price, $reason, $price);
        });
    }

    /**
     * Manual / emergency close, triggered by an admin action.
     *
     * If the signal is still PENDING (price never reached entry), this
     * is a cancel — no trade actually existed, so no P/L is computed
     * and no trade log is written. Otherwise it closes the live trade
     * exactly as before, always fetching the live price server-side.
     */
    public function closeManually(TradingSignal $signal, ?int $closedByUserId = null): ?TradeLog
    {
        return DB::transaction(function () use ($signal, $closedByUserId) {
            $locked = TradingSignal::where('id', $signal->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new \RuntimeException('Signal no longer exists.');
            }

            if (in_array($locked->status, ['closed', 'cancelled'], true)) {
                throw new \RuntimeException('Signal is already closed.');
            }

            // Pending signal → cancel. Never reached entry, so it was
            // never a real trade: no profit/loss, no log.
            if ($locked->status === 'pending') {
                return $this->cancelSignal($locked, $closedByUserId);
            }

            $priceData = $this->goldPriceService->getPrice();
            $price = (float) ($priceData['price'] ?? 0);

            if ($price <= 0) {
                throw new \RuntimeException('Live gold price is currently unavailable. Try again shortly.');
            }

            return $this->closeSignal($locked, $price, 'manual', $price, $closedByUserId);
        });
    }

    /**
     * Cancel a pending signal. No trade log, no P/L — it never became a
     * trade. Fires a "Signal cancelled" notification + a realtime event
     * so the dashboards drop it from the active card.
     */
    public function cancelSignal(TradingSignal $signal, ?int $closedByUserId = null): ?TradeLog
    {
        $signal->update([
            'status' => 'cancelled',
            'closed_at' => now(),
        ]);

        $this->notifyCancelled($signal);

        // Emit with a null trade_log so listeners know this was a cancel,
        // not a P/L-bearing close.
        event(new TeamSignalUpdated($signal->fresh(), null));

        Log::info('Trading signal cancelled (pending, entry never reached)', [
            'signal_id' => $signal->id,
            'entry_price' => $signal->entry_price,
        ]);

        return null;
    }

    protected function resolveTrigger(TradingSignal $signal, float $price): ?string
    {
        if ($signal->signal_type === 'buy') {
            if ($price >= (float) $signal->take_profit) return 'tp';
            if ($price <= (float) $signal->stop_loss) return 'sl';
        } else {
            if ($price <= (float) $signal->take_profit) return 'tp';
            if ($price >= (float) $signal->stop_loss) return 'sl';
        }

        return null;
    }

    /**
     * Shared close logic for genuinely live (open) trades. Used by the
     * auto-monitor and the manual-close flow. Never called for pending
     * signals — those go through cancelSignal().
     */
    public function closeSignal(
        TradingSignal $signal,
        float $closePrice,
        string $reason,
        ?float $goldPriceAtClose = null,
        ?int $closedByUserId = null
    ): TradeLog {
        $profitLoss = $signal->signal_type === 'buy'
            ? $closePrice - $signal->entry_price
            : $signal->entry_price - $closePrice;

        $result = $profitLoss > 0 ? 'profit' : ($profitLoss < 0 ? 'loss' : 'breakeven');
        $closedAt = now();

        $signal->update([
            'status' => 'closed',
            'closed_at' => $closedAt,
        ]);

        $tradeLog = TradeLog::create([
            'trading_signal_id' => $signal->id,
            'symbol' => $signal->symbol,
            'signal_type' => $signal->signal_type,
            'gold_price_at_entry' => $signal->gold_price_at_entry,
            'gold_price_at_close' => $goldPriceAtClose,
            'entry_price' => $signal->entry_price,
            'close_price' => $closePrice,
            'stop_loss' => $signal->stop_loss,
            'take_profit' => $signal->take_profit,
            'profit_loss' => $profitLoss,
            'result' => $result,
            'close_reason' => $reason,
            'closed_by' => $closedByUserId,
            'closed_at' => $closedAt,
        ]);

        $this->notifyTeam($signal, $result, $reason, $profitLoss);

        event(new TeamSignalUpdated($signal->fresh(), $tradeLog));

        Log::info('Trading signal closed', [
            'signal_id' => $signal->id,
            'reason' => $reason,
            'result' => $result,
            'profit_loss' => $profitLoss,
        ]);

        return $tradeLog;
    }

    protected function notifyTeam(TradingSignal $signal, string $result, string $reason, float $profitLoss): void
    {
        $reasonLabel = match ($reason) {
            'tp' => 'Take Profit hit',
            'sl' => 'Stop Loss hit',
            'manual' => 'Manually closed',
            default => 'Closed',
        };

        $title = $result === 'profit' ? 'Trade closed — Profit' : ($result === 'loss' ? 'Trade closed — Loss' : 'Trade closed — Breakeven');

        $sign = $profitLoss >= 0 ? '+' : '';
        $message = strtoupper($signal->symbol) . " {$reasonLabel}. Result: {$sign}" . number_format($profitLoss, 2) . '.';

        $this->pushNotification($title, $message);
    }

    protected function notifyActivated(TradingSignal $signal): void
    {
        $title = 'Trade activated';
        $message = strtoupper($signal->symbol) . ' ' . strtoupper($signal->signal_type)
            . ' trade is now live at entry ' . $signal->entry_price . '.';

        $this->pushNotification($title, $message);
    }

    protected function notifyCancelled(TradingSignal $signal): void
    {
        $title = 'Signal cancelled';
        $message = strtoupper($signal->symbol) . ' ' . strtoupper($signal->signal_type)
            . ' signal cancelled — price never reached entry ' . $signal->entry_price . '.';

        $this->pushNotification($title, $message);
    }

    protected function pushNotification(string $title, string $message): void
    {
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
                    $id => ['read_at' => null, 'created_at' => now(), 'updated_at' => now()],
                ])->toArray()
            );
        }
    }
}