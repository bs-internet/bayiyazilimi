<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Currency;
use App\Models\Setting;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Illuminate\Support\Collection;

/**
 * Vitrin para birimi yönetimi: aktif para birimleri, ziyaretçinin seçtiği
 * para birimi (oturumda) ve kurlar üzerinden dönüşüm.
 *
 * Kurlar TL bazlıdır: `exchange_rate` = 1 birimin TL karşılığı.
 */
class CurrencyManager
{
    public const SESSION_KEY = 'shop.currency';

    /** @var Collection<int, Currency>|null */
    private ?Collection $active = null;

    private ?Currency $base = null;

    /**
     * Vitrinde seçilebilen (aktif) para birimleri.
     *
     * @return Collection<int, Currency>
     */
    public function active(): Collection
    {
        return $this->active ??= Currency::query()->active()->ordered()->get();
    }

    /**
     * Varsayılan para birimi (genel ayarlardaki varsayılan, yoksa TRY).
     */
    public function base(): Currency
    {
        if ($this->base !== null) {
            return $this->base;
        }

        $id = Setting::get('default_currency_id');

        return $this->base = ($id ? Currency::query()->find($id) : null)
            ?? Currency::query()->where('code', 'TRY')->first()
            ?? new Currency([
                'code' => 'TRY', 'name' => __('Türk Lirası'), 'symbol' => '₺', 'decimal_places' => 2,
                'thousands_separator' => '.', 'decimal_separator' => ',', 'exchange_rate' => 1,
            ]);
    }

    /**
     * Ziyaretçinin seçtiği para birimi.
     */
    public function current(): Currency
    {
        $code = session(self::SESSION_KEY);

        if ($code) {
            $match = $this->active()->firstWhere('code', $code);
            if ($match !== null) {
                return $match;
            }
        }

        return $this->base();
    }

    public function setCurrent(string $code): bool
    {
        $match = $this->active()->firstWhere('code', strtoupper($code));

        if ($match === null) {
            return false;
        }

        session([self::SESSION_KEY => $match->code]);

        return true;
    }

    public function find(?string $code): ?Currency
    {
        if ($code === null) {
            return null;
        }

        return $this->active()->firstWhere('code', $code)
            ?? Currency::withTrashed()->where('code', $code)->first();
    }

    /**
     * Bir tutarı kaynaktan hedef para birimine çevirir (TL üzerinden).
     */
    public function convert(BigNumber|int|string $amount, ?Currency $from, Currency $to): BigDecimal
    {
        if ($from !== null && $from->code === $to->code) {
            return Money::decimal($amount);
        }

        // Kesin çapraz kur (TL üzerinden; sıfır / boş kur 1 sayılır). Birim fiyatlar
        // 4 haneli tutulduğu için ara sonuç 6 hane.
        return Money::convertWithRates($amount, $from === null ? 1 : $from->exchange_rate, $to->exchange_rate, 6);
    }

    public function format(BigNumber|int|string $amount, ?Currency $currency = null): string
    {
        return ($currency ?? $this->current())->format($amount);
    }
}
