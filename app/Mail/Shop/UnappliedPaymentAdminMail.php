<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnappliedPaymentAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Siparişe uygulanamayan ödeme: :number', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.unapplied-payment-admin',
        );
    }
}
