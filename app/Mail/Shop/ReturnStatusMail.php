<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\ReturnRequest;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — İade talebiniz: :status (:number)', ['app' => Branding::name(), 'status' => $this->returnRequest->status->label(), 'number' => $this->returnRequest->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.return-status',
        );
    }
}
