<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserBlocked extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function build()
    {
        return $this->subject('Your Market Sharks account has been blocked')
            ->markdown('emails.user-blocked');
    }
}