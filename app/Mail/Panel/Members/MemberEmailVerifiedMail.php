<?php

declare(strict_types=1);

namespace App\Mail\Panel\Members;

use App\Models\User;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberEmailVerifiedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — E-posta Adresiniz Onaylandı', ['app' => Branding::name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.panel.members.email-verified',
            with: [
                'user' => $this->user,
            ],
        );
    }
}
