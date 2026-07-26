<?php
namespace App\Mail;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Subscription $subscription) {}

    public function build()
    {
        return $this->subject('Your Market Sharks subscription expires soon')
            ->markdown('emails.subscription-expiring-soon');
    }
}