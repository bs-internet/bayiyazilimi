<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountTransaction;
use App\Models\Currency;
use App\Models\Order;
use App\Services\Shop\CurrencyManager;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Brick\Money\AllocationMode;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money as BrickMoney;

/**
 * Ana para birimi yardımcıları. Ana birim Genel Ayarlar'daki varsayılan para
 * birimidir (yoksa TL); cari, raporlar ve başlangıç ekranı bu birimle tutulur.
 * Kurlar TL üzerinden saklanır (`exchange_rate` = 1 birimin TL karşılığı),
 * ana birime çapraz kurla çevrilir: tutar × kur(belge) / kur(ana birim).
 */
final class Money
{
    public static function base(): Currency
    {
        return app(CurrencyManager::class)->base();
    }

    public static function baseCode(): string
    {
        return self::base()->code;
    }

    /** Ana birimin TL kuru (TL ana birimse 1). */
    public static function baseRate(): BigDecimal
    {
        $base = self::base();
        $rate = self::decimal($base->exchange_rate);

        return $base->code === 'TRY' || ! $rate->isPositive() ? BigDecimal::one() : $rate;
    }

    /*
     * ── Kesin hesap (brick/money) ─────────────────────────────────────────
     * Para hesabı float ile değil `Brick\Money\Money` ile yapılır: tutarlar
     * 2 hanede, yarım yukarı (HalfUp) yuvarlanır; ara çarpım / bölmeler
     * (kur, KDV, oran) kesirli (BigRational) yürür, yalnız sonuç yuvarlanır.
     * Para birimi bizim `currencies` tablomuzdaki koddur (ISO dışı da olabilir),
     * biçimlendirme `Currency::format()` ile kalır.
     */

    /** @var array<string, BrickCurrency> */
    private static array $brickCurrencies = [];

    /** Hesap için para birimi (2 kuruş hanesi); kod verilmezse ana birim. */
    public static function currency(?string $code = null): BrickCurrency
    {
        $code = strtoupper($code ?? self::baseCode());

        return self::$brickCurrencies[$code] ??= new BrickCurrency($code, null, $code, 2);
    }

    /**
     * Tutarı kesin para değerine çevirir (2 hane, yarım yukarı). Veritabanındaki
     * decimal sütunlar metin olarak gelir; float verilirse ondalık gösterimiyle alınır.
     */
    public static function of(BigNumber|int|float|string|null $amount, ?string $code = null): BrickMoney
    {
        return BrickMoney::of(self::decimal($amount), self::currency($code), roundingMode: RoundingMode::HalfUp);
    }

    public static function zero(?string $code = null): BrickMoney
    {
        return BrickMoney::zero(self::currency($code));
    }

    /** Sayıyı kesin ondalığa çevirir (float → sabit 10 haneli gösterim; bilimsel gösterim yok). */
    public static function decimal(BigNumber|int|float|string|null $value): BigDecimal
    {
        return match (true) {
            $value instanceof BigDecimal => $value,
            $value instanceof BigNumber => $value->toScale(10, RoundingMode::HalfUp)->strippedOfTrailingZeros(),
            $value === null || $value === '' => BigDecimal::zero(),
            is_float($value) => BigDecimal::of(sprintf('%.10F', $value))->strippedOfTrailingZeros(),
            default => BigDecimal::of($value),
        };
    }

    /**
     * Yalnız sunum için (grafik değeri, Excel hücresi): kesin tutarın float karşılığı.
     * Hesapta ve JSON / ERP sınırında kullanılmaz; orada BigDecimal / metin kalır.
     */
    public static function toFloat(BrickMoney|BigNumber|int|string|null $value): float
    {
        return $value instanceof BrickMoney ? $value->getAmount()->toFloat() : self::decimal($value)->toFloat();
    }

    /** Toplam: tutarların kesin toplamı. */
    public static function sum(iterable $amounts, ?string $code = null): BrickMoney
    {
        $total = self::zero($code);

        foreach ($amounts as $amount) {
            $total = $total->plus($amount instanceof BrickMoney ? $amount : self::of($amount, $code));
        }

        return $total;
    }

    /*
     * Para birimsiz kesin aritmetik (belge satırları, fiyat, kur). Tüm tutarlar
     * `Brick\Math\BigDecimal` olarak döner: ara sonuç kesirli yürür, yalnız
     * dönüşte istenen haneye (varsayılan 2) yarım yukarı (HalfUp) yuvarlanır.
     * Birim fiyatlar 4 hanede, satır / KDV / toplam 2 hanede tutulur.
     *
     * Sınırlar: BigDecimal JSON'a (Inertia, ERP) metin olarak çıkar ("1234.50");
     * ön yüz de kuruş tabanlı aynı kurallarla hesaplar (resources/js/lib/decimal.ts).
     * Float yalnız grafik / Excel hücresi gibi sunum noktalarında (`toFloat`).
     */

    /** Kesin yuvarlama. */
    public static function round(BigNumber|int|float|string|null $value, int $scale = 2): BigDecimal
    {
        return self::decimal($value)->toScale($scale, RoundingMode::HalfUp);
    }

    /** Veritabanı / JSON için sabit haneli metin ("1234.50"). */
    public static function str(BigNumber|int|float|string|null $value, int $scale = 2): string
    {
        return (string) self::round($value, $scale);
    }

    /** Kesin toplam (2 hane). */
    public static function add(BigNumber|int|float|string|null ...$values): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($values as $value) {
            $sum = $sum->plus(self::decimal($value));
        }

        return $sum->toScale(2, RoundingMode::HalfUp);
    }

    /** Fark: a − b (2 hane). */
    public static function sub(BigNumber|int|float|string|null $a, BigNumber|int|float|string|null $b): BigDecimal
    {
        return self::decimal($a)->minus(self::decimal($b))->toScale(2, RoundingMode::HalfUp);
    }

    /** Çarpım: tutar × çarpan (ör. birim fiyat × adet). */
    public static function times(BigNumber|int|float|string|null $amount, BigNumber|int|float|string|null $factor, int $scale = 2): BigDecimal
    {
        return self::decimal($amount)->multipliedBy(self::decimal($factor))->toScale($scale, RoundingMode::HalfUp);
    }

    /** Bölüm: tutar ÷ bölen (ör. ortalama); bölen sıfırsa sıfır. */
    public static function div(BigNumber|int|float|string|null $amount, BigNumber|int|float|string|null $divisor, int $scale = 2): BigDecimal
    {
        $divisor = self::decimal($divisor);

        return $divisor->isZero()
            ? BigDecimal::zero()->toScale($scale)
            : self::decimal($amount)->toBigRational()->dividedBy($divisor)->toScale($scale, RoundingMode::HalfUp);
    }

    /** Yüzde: tutar × oran / 100 (ör. KDV, iskonto, vade farkı). */
    public static function percent(BigNumber|int|float|string|null $amount, BigNumber|int|float|string|null $rate, int $scale = 2): BigDecimal
    {
        return self::decimal($amount)->toBigRational()->multipliedBy(self::decimal($rate))->dividedBy(100)
            ->toScale($scale, RoundingMode::HalfUp);
    }

    /** Oranla artırılmış tutar: tutar × (1 + oran / 100) (ör. KDV dahil fiyat). */
    public static function withRate(BigNumber|int|float|string|null $amount, BigNumber|int|float|string|null $rate, int $scale = 2): BigDecimal
    {
        return self::decimal($amount)->toBigRational()->multipliedBy(self::decimal($rate)->toBigRational()->dividedBy(100)->plus(1))
            ->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * Belge satırı: net = birim net × adet − indirim; KDV = net × oran; brüt = net + KDV (2 hane).
     *
     * @return array{net: BigDecimal, tax: BigDecimal, gross: BigDecimal}
     */
    public static function line(BigNumber|int|float|string|null $unitNet, int|float $quantity, BigNumber|int|float|string|null $taxRate, BigNumber|int|float|string|null $discount = 0): array
    {
        $net = self::decimal($unitNet)->multipliedBy(self::decimal($quantity))->minus(self::decimal($discount))
            ->toScale(2, RoundingMode::HalfUp);
        $tax = $net->toBigRational()->multipliedBy(self::decimal($taxRate))->dividedBy(100)->toScale(2, RoundingMode::HalfUp);

        return ['net' => $net, 'tax' => $tax, 'gross' => $net->plus($tax)];
    }

    /**
     * Bir tutarı ağırlıklara göre kuruşu kuruşuna paylaştırır (toplam korunur;
     * artan kuruşlar en büyük kalanlılardan başlayarak dağıtılır).
     *
     * @param  array<array-key, BigNumber|int|float|string>  $weights
     * @return array<array-key, BigDecimal>
     */
    public static function allocate(BigNumber|int|float|string $amount, array $weights): array
    {
        $keys = array_keys($weights);
        // Ağırlıklar kuruşa çevrilip tam sayı oran olarak verilir.
        $ratios = array_map(fn ($w) => max(0, self::decimal($w)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt()), array_values($weights));

        if ($keys === [] || array_sum($ratios) === 0) {
            return array_fill_keys($keys, BigDecimal::zero()->toScale(2));
        }

        $parts = self::of($amount, 'XXX')->allocate($ratios, AllocationMode::FloorToLargestRemainder);

        return array_combine($keys, array_map(fn (BrickMoney $m) => $m->getAmount(), $parts));
    }

    /** En küçüğü (kesin karşılaştırma). */
    public static function min(BigNumber|int|float|string|null $first, BigNumber|int|float|string|null ...$others): BigDecimal
    {
        return BigDecimal::min(self::decimal($first), ...array_map(self::decimal(...), $others));
    }

    /** En büyüğü (kesin karşılaştırma). */
    public static function max(BigNumber|int|float|string|null $first, BigNumber|int|float|string|null ...$others): BigDecimal
    {
        return BigDecimal::max(self::decimal($first), ...array_map(self::decimal(...), $others));
    }

    /**
     * Bir para birimindeki tutarı ana birime çevirir (güncel kurla).
     */
    public static function toBase(BigNumber|int|float|string|null $amount, ?Currency $from): BigDecimal
    {
        if ($from === null || $from->code === self::baseCode()) {
            return self::round($amount);
        }

        $rate = $from->code === 'TRY' ? '1' : (string) $from->exchange_rate;

        return self::convertWithRates($amount, $rate, self::baseRate());
    }

    /**
     * Belgenin kendi kurlarıyla ana birime çevirir: tutar × TL kuru / ana birimin TL kuru.
     */
    public static function convertWithRates(BigNumber|int|float|string|null $amount, BigNumber|int|float|string|null $tlRate, BigNumber|int|float|string|null $baseTlRate, int $scale = 2): BigDecimal
    {
        $tlRate = self::decimal($tlRate);
        $baseTlRate = self::decimal($baseTlRate);
        $tlRate = $tlRate->isPositive() ? $tlRate : BigDecimal::one();
        $baseTlRate = $baseTlRate->isPositive() ? $baseTlRate : BigDecimal::one();

        return self::decimal($amount)->toBigRational()->multipliedBy($tlRate)->dividedBy($baseTlRate)
            ->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * Para birimi sembolü olmadan Türkçe sayı biçimi ("1.234,50"); kesin (float yok).
     */
    public static function number(BigNumber|int|float|string|null $amount, int $scale = 2): string
    {
        $value = self::round($amount, $scale);
        [$whole, $fraction] = array_pad(explode('.', (string) $value->abs()), 2, '');
        $whole = strrev(implode('.', str_split(strrev($whole), 3)));

        return ($value->isNegative() ? '-' : '').$whole.($fraction !== '' ? ','.$fraction : '');
    }

    /** @var array<string, Currency|null> */
    private static array $currenciesByCode = [];

    /**
     * Belgenin kendi para biriminde biçimlendirir (silinmiş birim de bulunur);
     * birim tanımlı değilse "1.234,50 KOD".
     */
    public static function formatIn(BigNumber|int|float|string|null $amount, ?string $code): string
    {
        if ($code === null || $code === self::baseCode()) {
            return self::format($amount);
        }

        if (! array_key_exists($code, self::$currenciesByCode)) {
            self::$currenciesByCode[$code] = Currency::withTrashed()->where('code', $code)->first();
        }

        return self::$currenciesByCode[$code]?->format($amount) ?? self::number($amount).' '.$code;
    }

    /** Değişim yüzdesi (önceki dönem sıfırsa null), 1 hane. */
    public static function change(BigNumber|int|float|string|null $current, BigNumber|int|float|string|null $previous): ?float
    {
        $previous = self::decimal($previous);

        if ($previous->isZero()) {
            return null;
        }

        return self::div(self::decimal($current)->minus($previous)->multipliedBy(100), $previous, 1)->toFloat();
    }

    public static function format(BrickMoney|BigNumber|int|float|string|null $amount): string
    {
        return self::base()->format($amount instanceof BrickMoney ? $amount->getAmount() : $amount);
    }

    /**
     * Siparişteki tutarı siparişin kendi kurlarıyla ana birime çeviren SQL ifadesi
     * (raporlar, başlangıç ekranı): tutar × kur(sipariş, TL) / kur(ana birim, TL).
     */
    public static function sqlToBase(string $column, ?string $table = null, bool $storedBaseRate = true): string
    {
        $t = $table !== null ? "{$table}." : '';
        // Para birimi kodu SQL metnine girer: yalnız üç büyük harfe izin verilir.
        $base = self::baseCode();
        if (preg_match('/^[A-Z]{3}$/', $base) !== 1) {
            throw new \InvalidArgumentException("Geçersiz ana para birimi kodu: {$base}");
        }
        // Siparişte ana birimin o anki kuru saklıdır; saklamayan tablolarda (teklif) güncel kur.
        $baseRate = $storedBaseRate ? "coalesce(nullif({$t}base_exchange_rate, 0), 1)" : (string) self::baseRate();

        return "(case when {$t}currency_code = '{$base}' then {$column} else {$column}"
            ." * (case when {$t}currency_code = 'TRY' then 1 else coalesce(nullif({$t}exchange_rate, 0), 1) end)"
            ." / {$baseRate} end)";
    }

    /**
     * Ana para birimi kilitli mi: ilk sipariş ya da ilk cari hareketten sonra
     * değiştirilemez (geçmiş tutarlar o birimle yazıldı).
     */
    public static function baseLocked(): bool
    {
        return Order::query()->withoutGlobalScopes()->exists()
            || AccountTransaction::query()->withoutGlobalScopes()->exists();
    }
}
