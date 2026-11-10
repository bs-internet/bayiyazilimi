<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\User;

/**
 * REST API anahtarlarının yetkileri (Sanctum "abilities"). Her uç nokta
 * `abilities:{yetki}` ara katmanıyla bunlardan birini ister. Eklentiler kendi
 * uç noktalarının yetkilerini `api.abilities` filtresiyle ekler
 * (anahtar => Türkçe etiket); anahtar ekranı listeyi buradan kurar.
 */
final class ApiAbilities
{
    public const PRODUCTS_READ = 'products:read';

    public const PRODUCTS_WRITE = 'products:write';

    public const STOCK_WRITE = 'stock:write';

    public const ORDERS_READ = 'orders:read';

    public const ORDERS_WRITE = 'orders:write';

    public const ACCOUNTS_READ = 'accounts:read';

    public const LEDGER_WRITE = 'ledger:write';

    /** API yetkisi => panelde aynı işi yapan rotanın izni. */
    private const PERMISSIONS = [
        self::PRODUCTS_READ => 'panel.shop.products.index',
        self::PRODUCTS_WRITE => 'panel.shop.products.update',
        self::STOCK_WRITE => 'panel.shop.stock.adjust',
        self::ORDERS_READ => 'panel.shop.orders.index',
        self::ORDERS_WRITE => 'panel.shop.orders.status',
        self::ACCOUNTS_READ => 'panel.shop.ledger.index',
        self::LEDGER_WRITE => 'panel.shop.ledger.adjust',
    ];

    /**
     * Tüm yetkiler: anahtar => etiket (çekirdek + eklentiler).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $core = [
            self::PRODUCTS_READ => __('Ürünleri okuma'),
            self::PRODUCTS_WRITE => __('Ürün güncelleme (fiyat, durum)'),
            self::STOCK_WRITE => __('Stok miktarı yazma'),
            self::ORDERS_READ => __('Siparişleri okuma'),
            self::ORDERS_WRITE => __('Sipariş durumu ve fatura bilgisi yazma'),
            self::ACCOUNTS_READ => __('Cari hesapları okuma'),
            self::LEDGER_WRITE => __('Cari hareket yazma (tahsilat, dekont)'),
        ];

        $extra = hooks()->apply('api.abilities', []);

        return [...$core, ...(is_array($extra) ? $extra : [])];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Kullanıcının anahtara verebileceği yetkiler: panelde aynı işi yapma izni
     * olanlar. Eşlemesi olmayan (eklentinin eklediği) yetkiler serbesttir.
     *
     * @return list<string>
     */
    public static function grantableBy(User $user): array
    {
        return array_values(array_filter(
            self::keys(),
            fn (string $ability): bool => ! isset(self::PERMISSIONS[$ability]) || $user->can(self::PERMISSIONS[$ability]),
        ));
    }

    /**
     * Seçenek listesi (anahtar ekranı): kullanıcının verebileceği yetkiler.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(User $user): array
    {
        $labels = self::all();

        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => (string) $labels[$value]],
            self::grantableBy($user),
        );
    }
}
