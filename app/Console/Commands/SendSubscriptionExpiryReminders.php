<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:remind-expiring';

    protected $description = 'Email users whose subscription expires within 7 days, once per subscription';

    public function handle()
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereNull('expiry_reminder_sent_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->with('user')
            ->get();

        $count = 0;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->user) {
                continue;
            }

            try {
                Mail::to($subscription->user->email)->send(
                    new SubscriptionExpiringSoon($subscription->user, $subscription)
                );
            } catch (\Throwable $e) {
                \Log::warning('Expiry reminder email failed', [
                    'user_id' => $subscription->user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $subscription->update(['expiry_reminder_sent_at' => now()]);
            $count++;
        }

        $this->info("Sent {$count} expiry reminder(s).");
    }
}