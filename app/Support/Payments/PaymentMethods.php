<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\Shop\CollectionType;

/**
 * Ödeme yöntemi anahtarının görünen adı: önce kayıtlı sürücü, sonra elle
 * tahsilat türü; ikisi de yoksa anahtarın kendisi (kaldırılmış modülün eski
 * kayıtları da okunur).
 */
final class PaymentMethods
{
    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return app(PaymentRegistry::class)->get($key)?->label()
            ?? CollectionType::tryFrom($key)?->label()
            ?? $key;
    }

    /**
     * Sipariş filtreleri için sürücüler (+ kayıtlarda geçen ama artık kayıtlı olmayan anahtarlar).
     *
     * @param  array<int, string>  $extraKeys
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(array $extraKeys = []): array
    {
        $keys = array_values(array_unique([...array_keys(app(PaymentRegistry::class)->all()), ...$extraKeys]));

        return array_map(fn (string $k) => ['value' => $k, 'label' => self::label($k)], $keys);
    }
}
