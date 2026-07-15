<?php

namespace App\Console\Commands;

use App\Models\TradingSignal;
use App\Services\SignalCloseService;
use Illuminate\Console\Command;

class SignalsMonitor extends Command
{
    protected $signature = 'signals:monitor';
    protected $description = 'Watch the open trading signal and auto-close it when TP or SL is hit.';

    public function handle(SignalCloseService $service): int
    {
        if (!TradingSignal::where('status', 'open')->exists()) {
            return self::SUCCESS;
        }

        $deadline = now()->addSeconds(280); // stay awake ~4m40s, just under the 5-min cron gap

        while (now()->lt($deadline)) {
            $service->checkAndCloseIfTriggered();

            if (!TradingSignal::where('status', 'open')->exists()) {
                break;
            }

            sleep(5);
        }

        return self::SUCCESS;
    }
}