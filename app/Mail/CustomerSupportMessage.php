<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerSupportMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerEmail,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'VOID Clothing support request',
            replyTo: [$this->customerEmail],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-support',
        );
    }
}
