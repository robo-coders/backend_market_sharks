<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminInviteNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     */
    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));

        return (new MailMessage)
            ->subject('Welcome to Market Sharks — Set your password')
            ->greeting('Welcome to Market Sharks!')
            ->line('You have been invited as an Admin.')
            ->line('To activate your account, please set your password.')
            ->action('Set Password', $url)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
