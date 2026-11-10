<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Kayıtlı adresin kullanımı: her adres ya teslimat ya fatura adresidir.
 * Aynı adres iki amaçla kullanılacaksa iki ayrı kayıt tutulur.
 */
enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::Shipping => __('Teslimat'),
            self::Billing => __('Fatura'),
        };
    }

    public function other(): self
    {
        return $this === self::Shipping ? self::Billing : self::Shipping;
    }

    /** Bu türün varsayılan bayrağı (customer_addresses sütunu). */
    public function defaultColumn(): string
    {
        return $this === self::Shipping ? 'is_default_shipping' : 'is_default_billing';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
