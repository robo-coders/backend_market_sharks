<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use Illuminate\Console\Command;

class PruneChatMessages extends Command
{
    protected $signature = 'chat:prune {--days=30 : Delete messages older than this many days}';

    protected $description = 'Permanently delete chat messages older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = ChatMessage::withTrashed()
            ->where('created_at', '<', $cutoff)
            ->forceDelete();

        $this->info("Pruned {$count} chat message(s) older than {$days} days.");

        return self::SUCCESS;
    }
}