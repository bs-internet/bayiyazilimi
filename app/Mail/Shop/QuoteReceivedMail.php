<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Quote;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Teklif talebiniz alındı (:number)', ['app' => Branding::name(), 'number' => $this->quote->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.quote-received',
        );
    }
}
