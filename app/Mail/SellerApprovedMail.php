<?php

namespace App\Mail;

use App\Models\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SellerApplication $application
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Your Seller Application Has Been Approved — ALVY',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller.approved',
        );
    }
}
