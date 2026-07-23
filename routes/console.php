<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Schedule::command('subscriptions:expire')->dailyAt('00:00');
Schedule::command('subscriptions:remind-expiring')->dailyAt('09:00');
Schedule::command('chat:prune --days=30')->daily();

// signals:monitor is NOT scheduled here — it runs as a persistent daemon
// under supervisor (/etc/supervisor/conf.d/signals-monitor.conf) so it can
// poll the live gold price every 5 seconds and close on TP/SL immediately.
// Scheduling it would launch duplicate windowed runs alongside the daemon.
// Schedule::command('signals:monitor')->everyFiveMinutes()->withoutOverlapping(6);


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');