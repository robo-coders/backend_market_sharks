<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark expired subscriptions and reset user status to pending';

    public function handle()
    {
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->with('user')
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            if ($subscription->user) {
                $subscription->user->update(['status' => 'pending']);
            }

            $count++;
        }

        $this->info("Expired {$count} subscription(s).");
    }
}