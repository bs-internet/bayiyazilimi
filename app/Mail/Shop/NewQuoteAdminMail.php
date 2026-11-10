<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewQuoteAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Yeni teklif talebi: :number', ['number' => $this->quote->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-quote-admin',
        );
    }
}
