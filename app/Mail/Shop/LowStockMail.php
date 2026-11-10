<?php

declare(strict_types=1);

namespace App\Mail\Shop;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->product->stock <= 0
                ? __('Stok tükendi: :sku', ['sku' => $this->product->sku])
                : __('Kritik stok: :sku', ['sku' => $this->product->sku]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.low-stock',
        );
    }
}
