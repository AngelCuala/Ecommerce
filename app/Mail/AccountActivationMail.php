<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The set-password link for a rider or courier made by a logistics company. */
class AccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $link,
        public readonly string $company,
        public readonly int $hours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activate your ALVY account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.account-activation');
    }
}
