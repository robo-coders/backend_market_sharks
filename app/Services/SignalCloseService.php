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
     * Called by the scheduled command. Checks the single open signal
     * (if any) against the live price and closes it if TP or SL was hit.
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
     * Manual / emergency close, triggered by an admin action. Ignores
     * SL/TP entirely and always fetches the live price server-side
     * (never trusts a client-supplied price) so it can't be spoofed or
     * stale. Profit/loss is entry-relative, same formula as the
     * automatic close, just without a tp/sl trigger reason.
     */
    public function closeManually(TradingSignal $signal, ?int $closedByUserId = null): TradeLog
    {
        return DB::transaction(function () use ($signal, $closedByUserId) {
            $locked = TradingSignal::where('id', $signal->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new \RuntimeException('Signal no longer exists.');
            }

            if ($locked->status !== 'open') {
                throw new \RuntimeException('Signal is already closed.');
            }

            $priceData = $this->goldPriceService->getPrice();
            $price = (float) ($priceData['price'] ?? 0);

            if ($price <= 0) {
                throw new \RuntimeException('Live gold price is currently unavailable. Try again shortly.');
            }

            return $this->closeSignal($locked, $price, 'manual', $price, $closedByUserId);
        });
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
     * Shared close logic. Used by the auto-monitor command and the
     * manual-close flow above.
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