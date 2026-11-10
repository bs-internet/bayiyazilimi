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

class OrderStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Sipariş durumu: :status (:number)', ['app' => Branding::name(), 'status' => $this->order->status->label(), 'number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.order-status',
        );
    }
}
