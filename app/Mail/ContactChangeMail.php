<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The code that confirms a change of email address or phone number. */
class ContactChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly string $what,      // "email address" | "phone number"
        public readonly string $newValue,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your new ' . $this->what . ': ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.contact-change');
    }
}
