<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

/**
 * Webhook olayları: anahtar => Türkçe etiket. Eklentiler `webhooks.events`
 * filtresiyle kendi olaylarını ekler ve `WebhookService::dispatch()` ile gönderir.
 */
final class WebhookEvents
{
    public const ORDER_CREATED = 'order.created';

    public const ORDER_STATUS_CHANGED = 'order.status_changed';

    public const ORDER_PAID = 'order.paid';

    public const ORDER_INVOICED = 'order.invoiced';

    public const ACCOUNT_TRANSACTION = 'account.transaction_recorded';

    public const STOCK_LOW = 'product.stock_low';

    public const RETURN_REQUESTED = 'return.requested';

    public const QUOTE_REQUESTED = 'quote.requested';

    public const PING = 'ping';

    /** @return array<string, string> */
    public static function all(): array
    {
        $core = [
            self::ORDER_CREATED => __('Sipariş oluşturuldu'),
            self::ORDER_STATUS_CHANGED => __('Sipariş durumu değişti'),
            self::ORDER_PAID => __('Sipariş ödendi'),
            self::ORDER_INVOICED => __('Sipariş faturalandı'),
            self::ACCOUNT_TRANSACTION => __('Cari hareket kaydedildi'),
            self::STOCK_LOW => __('Stok kritik seviyede'),
            self::RETURN_REQUESTED => __('İade talebi açıldı'),
            self::QUOTE_REQUESTED => __('Teklif istendi'),
        ];

        $extra = hooks()->apply('webhooks.events', []);

        return [...$core, ...(is_array($extra) ? $extra : [])];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
