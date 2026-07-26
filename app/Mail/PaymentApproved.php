<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Payment Has Been Approved – Market Sharks',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}