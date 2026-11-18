<?php

declare(strict_types=1);

namespace App\Mail\System;

use App\Events\System\ErrorReportedEvent;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ErrorReportedMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly ErrorReportedEvent $error) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app: sistemde hata oluştu', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.system.error-reported',
        );
    }
}
