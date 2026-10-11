<?php

namespace App\Mail;

use App\Models\SortingCenterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SortingCenterRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SortingCenterApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Update on Your Sorting Center Registration — ALVY');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sorting-center.rejected');
    }
}
