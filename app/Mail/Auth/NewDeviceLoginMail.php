<?php

declare(strict_types=1);

namespace App\Mail\Auth;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewDeviceLoginMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $loginAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app - Farklı Bir Cihazdan Oturum Açıldı', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.common.new-device-login',
            with: [
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'loginAt' => $this->loginAt,
            ],
        );
    }
}
