<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Models\Quote;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly ?string $note = null,
        public readonly bool $byCustomer = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->quote->status === QuoteStatus::Sent => __('Teklifiniz hazır'),
            $this->quote->status === QuoteStatus::Approved => __('Teklif onayınız alındı'),
            $this->quote->status === QuoteStatus::Revision => __('Revize isteğiniz alındı'),
            $this->quote->status === QuoteStatus::Rejected => __('Teklifi reddettiniz'),
            $this->byCustomer => __('Teklif talebiniz geri çekildi'),
            default => __('Teklifiniz iptal edildi'),
        };

        return new Envelope(
            subject: __(':app — :subject (:number)', ['app' => Branding::name(), 'subject' => $subject, 'number' => $this->quote->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.quote-status',
        );
    }
}
