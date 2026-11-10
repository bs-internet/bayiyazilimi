<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Yeni sipariş: :number', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-order-admin',
        );
    }
}
