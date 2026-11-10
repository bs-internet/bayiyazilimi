<?php

declare(strict_types=1);

namespace App\Mail\Auth;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $verificationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — E-posta Adresinizi Doğrulayın', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.common.verify-email',
            with: [
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }
}
