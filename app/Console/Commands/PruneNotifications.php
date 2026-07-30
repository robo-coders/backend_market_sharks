<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=15 : Delete notifications older than this many days}';

    protected $description = 'Permanently delete notifications older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = Notification::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}