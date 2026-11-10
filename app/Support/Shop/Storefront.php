<?php

declare(strict_types=1);

namespace App\Support\Shop;

/**
 * Herkese açık bir vitrin (mağaza) sunan modül kurulu ve açık mı?
 *
 * Çekirdek modülü adıyla tanımaz: vitrin sunan modül `shop.storefront_enabled`
 * filtresiyle `true` döner. Yalnız vitrinde işe yarayan alanlar (stoksuz ürün
 * gösterimi, kategori SEO'su, ETBİS, kampanya etiketi, sayfanın bayi panelinde
 * gösterilip gösterilmeyeceği) bu bayrak açıkken ekranda çıkar; kapalıyken
 * kayıtlı değerlere dokunulmaz.
 */
final class Storefront
{
    public static function enabled(): bool
    {
        return (bool) hooks()->apply('shop.storefront_enabled', false);
    }
}
