<?php

namespace App\Console\Commands;

use App\Models\MarketStructure;
use App\Models\TradingSignal;
use App\Services\LevelAlertService;
use App\Services\SignalCloseService;
use Illuminate\Console\Command;

class SignalsMonitor extends Command
{
    protected $signature = 'signals:monitor';
    protected $description = 'Watch the open trading signal and auto-close it when TP or SL is hit, and alert when price nears a support/resistance level.';

    public function handle(SignalCloseService $service, LevelAlertService $levels): int
    {
        $hasOpenSignal = TradingSignal::where('status', 'open')->exists();
        $hasLevels = MarketStructure::query()->exists();

        if (!$hasOpenSignal && !$hasLevels) {
            return self::SUCCESS;
        }

        $deadline = now()->addSeconds(280);

        while (now()->lt($deadline)) {
            if ($hasOpenSignal) {
                $service->checkAndCloseIfTriggered();
                $hasOpenSignal = TradingSignal::where('status', 'open')->exists();
            }

            $levels->checkAndNotify();

            if (!$hasOpenSignal && !$hasLevels) {
                break;
            }

            sleep(5);
        }

        return self::SUCCESS;
    }
}