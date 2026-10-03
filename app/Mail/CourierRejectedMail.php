<?php

namespace App\Mail;

use App\Models\Courier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourierRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Courier $courier
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on Your Courier Application — ALVY',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.courier.rejected',
        );
    }
}
