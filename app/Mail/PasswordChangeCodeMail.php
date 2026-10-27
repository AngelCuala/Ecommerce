<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The code that confirms a password change made from the account's security page. */
class PasswordChangeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $minutes,
        public readonly ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your password change: ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.password-change', text: 'emails.auth.password-change-text');
    }
}
