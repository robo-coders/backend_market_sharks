<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark expired subscriptions and reset user status to expired';

    public function handle()
    {
        $expired = Subscription::where('expires_at', '<', now())
            ->where('status', '!=', 'expired')
            ->where('status', '!=', 'canceled')
            ->with('user')
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            if ($subscription->user && $subscription->user->status === 'active') {
                $subscription->user->update(['status' => 'expired']);
            }

            $count++;
        }

        $this->info("Expired {$count} subscription(s).");
    }
}