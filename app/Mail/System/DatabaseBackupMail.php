<?php

declare(strict_types=1);

namespace App\Mail\System;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DatabaseBackupMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $fileName,
        public readonly int $size,
        public readonly ?string $archive,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app veritabanı yedeği (:date)', ['app' => Branding::name(), 'date' => now()->format('d.m.Y')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.system.database-backup',
            with: ['sizeMb' => number_format($this->size / 1048576, 1, ',', '.')],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->archive === null ? [] : [
            Attachment::fromPath($this->archive)->as(pathinfo($this->fileName, PATHINFO_FILENAME).'.zip')->withMime('application/zip'),
        ];
    }
}
