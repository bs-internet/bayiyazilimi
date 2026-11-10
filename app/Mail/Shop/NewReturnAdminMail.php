<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReturnAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Yeni iade talebi: :number', ['number' => $this->returnRequest->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-return-admin',
        );
    }
}
