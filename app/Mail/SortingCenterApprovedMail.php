<?php

namespace App\Mail;

use App\Models\SortingCenterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SortingCenterApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SortingCenterApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Sorting Center Registration Has Been Approved — ALVY');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sorting-center.approved');
    }
}
