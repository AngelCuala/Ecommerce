<?php

namespace App\Mail;

use App\Models\Courier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourierApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Courier $courier
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Courier Application Has Been Approved — ALVY',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.courier.approved',
        );
    }
}
