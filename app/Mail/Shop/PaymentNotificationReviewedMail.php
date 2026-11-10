<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Enums\Shop\PaymentNotificationStatus;
use App\Models\PaymentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentNotificationReviewedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PaymentNotification $paymentNotification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->paymentNotification->status === PaymentNotificationStatus::Approved
                ? __('Havale bildiriminiz onaylandı')
                : __('Havale bildiriminiz reddedildi'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.payment-notification-reviewed',
        );
    }
}
