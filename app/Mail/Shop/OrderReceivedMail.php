<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Order;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Siparişiniz alındı (:number)', ['app' => Branding::name(), 'number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.order-received',
        );
    }
}
