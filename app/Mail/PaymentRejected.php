<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $reason = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Review Update – Market Sharks',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}