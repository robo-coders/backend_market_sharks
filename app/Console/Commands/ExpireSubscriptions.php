<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpired;
use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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

                try {
                    Mail::to($subscription->user->email)->send(new SubscriptionExpired($subscription->user));
                } catch (\Throwable $e) {
                    Log::warning('Expired-subscription email failed to send', [
                        'user_id' => $subscription->user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $count++;
        }

        $this->info("Expired {$count} subscription(s).");
    }
}