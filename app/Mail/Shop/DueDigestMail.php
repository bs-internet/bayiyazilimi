<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Yöneticiye günlük vade özeti: bayi bazında vadesi geçen ve yaklaşan borçlar.
 */
class DueDigestMail extends Mailable
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $overdue,
        public readonly bool $upcoming,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Günlük vade özeti (:count bayi)', ['app' => Branding::name(), 'count' => count($this->rows)]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shop.due-digest');
    }
}
