<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\PaymentNotification;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewPaymentNotificationAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PaymentNotification $paymentNotification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Yeni havale bildirimi: :amount', ['amount' => Money::format($this->paymentNotification->amount)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-payment-notification-admin',
        );
    }
}
