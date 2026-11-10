<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Exceptions\Shop\ShopException;
use App\Support\Money;
use Brick\Math\BigNumber;
use Illuminate\Support\Facades\Cache;

/**
 * Sipariş gönderiminin iki koruması (sepet, kayıtlı sepet ve teklif ödemesi ortak):
 *
 * - **Tek kullanımlık ödeme anahtarı** (`checkout_token`): ödeme ekranı her
 *   açılışta yeni bir anahtar üretir; aynı anahtarla ikinci gönderim (çift tıklama,
 *   iki sekme, ağ tekrarı) reddedilir. Anahtar atomik olarak (Cache::add) alınır;
 *   sipariş oluşmazsa bırakılır ki müşteri düzeltip yeniden gönderebilsin.
 * - **Beklenen tutar** (`expected_total`): müşterinin ekranda gördüğü ödenecek
 *   tutar. Fiyat, kur, kampanya ya da il koşulu bu arada değiştiyse sipariş
 *   oluşmaz ve yeni tutar gösterilir; müşteri aynı formu yeniden gönderirse
 *   (yeni tutarı görmüş sayılır) sipariş o tutarla verilir.
 */
final class CheckoutGuard
{
    private const TOKEN_TTL = 7200;

    private const ACCEPTED_KEY = 'shop.checkout.accepted_total';

    /** Anahtarı alır; daha önce kullanıldıysa ShopException. Anahtar yoksa (eski istemci) serbest. */
    public static function claim(?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        if (! Cache::add(self::cacheKey($token), true, self::TOKEN_TTL)) {
            throw new ShopException(__('Bu sipariş zaten gönderildi; lütfen siparişlerinizi kontrol edin.'));
        }
    }

    /** Sipariş oluşmadıysa anahtar bırakılır. */
    public static function release(?string $token): void
    {
        if ($token !== null && $token !== '') {
            Cache::forget(self::cacheKey($token));
        }
    }

    /**
     * Hesaplanan tutar müşterinin gördüğüyle aynı mı. Farklıysa yeni tutar oturuma
     * yazılır ve ShopException; aynı tutarla yeniden gönderim kabul edilir.
     */
    public static function confirmTotal(BigNumber|string|null $expected, BigNumber|string $actual, string $currencyCode): void
    {
        if ($expected === null || $expected === '' || ! is_numeric((string) $expected)) {
            return;
        }

        $actual = Money::round($actual);

        if (Money::round((string) $expected)->isEqualTo($actual)) {
            return;
        }

        $accepted = session()->pull(self::ACCEPTED_KEY);

        if (is_string($accepted) && Money::round($accepted)->isEqualTo($actual)) {
            return;
        }

        session()->put(self::ACCEPTED_KEY, (string) $actual);

        throw new ShopException(__('Sipariş tutarı değişti: ödenecek tutar artık :total :currency. Kontrol edip siparişi yeniden onaylayın.', [
            'total' => Money::number($actual),
            'currency' => $currencyCode,
        ]));
    }

    private static function cacheKey(string $token): string
    {
        return 'shop:checkout:'.hash('sha256', $token);
    }
}
