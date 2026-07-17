<?php

namespace App\Console\Commands;

use App\Models\MarketStructure;
use App\Models\TradingSignal;
use App\Services\LevelAlertService;
use App\Services\SignalCloseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SignalsMonitor extends Command
{
    protected $signature = 'signals:monitor {--daemon : Run forever (like queue:work) instead of a single scheduler window}';
    protected $description = 'Watch the open trading signal (auto-close on TP/SL) and alert when price nears a support/resistance level.';

    public function handle(SignalCloseService $service, LevelAlertService $levels): int
    {
        $daemon = (bool) $this->option('daemon');
        $deadline = now()->addSeconds(280);
        $iteration = 0;

        $this->info($daemon ? 'signals:monitor running as daemon (Ctrl+C to stop)…' : 'signals:monitor running one scheduler window…');
        Log::info('signals:monitor started', ['daemon' => $daemon]);

        while ($daemon || now()->lt($deadline)) {
            $hasOpenSignal = TradingSignal::where('status', 'open')->exists();
            $hasLevels = MarketStructure::query()->exists();

            if ($hasOpenSignal) {
                $service->checkAndCloseIfTriggered();
            }

            if ($hasLevels) {
                $levels->checkAndNotify();
            }

            if ($iteration % 12 === 0) {
                Log::info('signals:monitor tick', [
                    'daemon' => $daemon,
                    'open_signal' => $hasOpenSignal,
                    'has_levels' => $hasLevels,
                ]);
            }

            $iteration++;

            if (!$daemon && !$hasOpenSignal && !$hasLevels) {
                break;
            }

            sleep(5);
        }

        return self::SUCCESS;
    }
}