<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Ürünün satış biçimi: doğrudan sepetten satın alma ya da yalnızca teklif ile.
 * Teklif ürünlerinde fiyat gösterilmez; ikisi birden (sepet + teklif) yoktur.
 */
enum SaleMode: string
{
    case Cart = 'cart';
    case Quote = 'quote';

    public function label(): string
    {
        return match ($this) {
            self::Cart => __('Sepet (doğrudan satış)'),
            self::Quote => __('Yalnızca teklif'),
        };
    }

    public function allowsCart(): bool
    {
        return $this !== self::Quote;
    }

    public function allowsQuote(): bool
    {
        return $this !== self::Cart;
    }
}
