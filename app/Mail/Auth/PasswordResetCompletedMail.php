<?php

declare(strict_types=1);

namespace App\Mail\Auth;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCompletedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Şifreniz Değiştirildi', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.common.password-reset-completed',
        );
    }
}
