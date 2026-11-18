<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Account;
use App\Services\Shop\LedgerExporter;
use App\Services\Shop\LedgerService;
use App\Support\Branding;
use App\Support\Ledger\LedgerPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Cari ekstre: dönem ve kapanış bakiyesi metinde, hareketler ekli Excel'de
 * (panelin "Excel" düğmesiyle aynı dosya).
 */
class AccountStatementMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Account $account,
        public readonly ?string $periodFrom,
        public readonly ?string $periodTo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — Cari hesap ekstreniz (:code)', ['app' => Branding::name(), 'code' => $this->account->code]),
        );
    }

    public function content(): Content
    {
        $statement = app(LedgerService::class)->statement(
            $this->account->id,
            $this->periodFrom ? CarbonImmutable::parse($this->periodFrom) : null,
            $this->periodTo ? CarbonImmutable::parse($this->periodTo) : null,
        );

        return new Content(
            markdown: 'mail.shop.account-statement',
            with: [
                'period' => ($this->periodFrom ? CarbonImmutable::parse($this->periodFrom)->format('d.m.Y') : __('Başlangıç'))
                    .' – '.($this->periodTo ? CarbonImmutable::parse($this->periodTo)->format('d.m.Y') : CarbonImmutable::today()->format('d.m.Y')),
                'closing' => LedgerPresenter::side($statement['closing']),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $account = $this->account;
        $from = $this->periodFrom;
        $to = $this->periodTo;

        return [
            Attachment::fromData(function () use ($account, $from, $to): string {
                $path = app(LedgerExporter::class)->export($account, $from, $to);
                $contents = (string) file_get_contents($path);
                @unlink($path);

                return $contents;
            }, 'cari-ekstre-'.Str::slug($account->code).'-'.CarbonImmutable::today()->format('Y-m-d').'.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
