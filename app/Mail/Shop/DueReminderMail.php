<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Account;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Vade hatırlatması: vadesi geçen ve yaklaşan açık borçlar (sipariş bazında).
 */
class DueReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $overdue
     * @param  array<int, array<string, mixed>>  $upcoming
     */
    public function __construct(
        public readonly Account $account,
        public readonly array $overdue,
        public readonly array $upcoming,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->overdue !== []
                ? __(':app — Vadesi geçen borcunuz var', ['app' => Branding::name()])
                : __(':app — Vadesi yaklaşan borcunuz var', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shop.due-reminder');
    }
}
