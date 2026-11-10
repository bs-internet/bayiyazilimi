<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Events\Shop\ExchangeRatesUpdatedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\User;
use App\Services\Panel\Tools\Definitions\CurrencyService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Döviz kurları: elle girilen kurlar ve TCMB'nin günlük kur XML'inden çekilen kurlar.
 *
 * Yalnızca `rate_source = tcmb` olan para birimleri TCMB'den güncellenir;
 * `manual` olanlara dokunulmaz. Varsayılan para birimi (TL) her zaman 1'dir.
 */
class ExchangeRateService
{
    public const TCMB_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    /**
     * TCMB kur türleri: XML alanı => etiket.
     *
     * @return array<string, string>
     */
    public static function rateTypes(): array
    {
        return [
            'ForexBuying' => __('Döviz Alış'),
            'ForexSelling' => __('Döviz Satış'),
            'BanknoteBuying' => __('Efektif Alış'),
            'BanknoteSelling' => __('Efektif Satış'),
        ];
    }

    /**
     * TCMB'nin yayımladığı para birimleri (XML'e ulaşılamazsa kullanılır).
     *
     * @var array<string, array{name: string, unit: int}>
     */
    public const TCMB_CURRENCIES = [
        'USD' => ['name' => 'ABD Doları', 'unit' => 1],
        'AUD' => ['name' => 'Avustralya Doları', 'unit' => 1],
        'DKK' => ['name' => 'Danimarka Kronu', 'unit' => 1],
        'EUR' => ['name' => 'Euro', 'unit' => 1],
        'GBP' => ['name' => 'İngiliz Sterlini', 'unit' => 1],
        'CHF' => ['name' => 'İsviçre Frangı', 'unit' => 1],
        'SEK' => ['name' => 'İsveç Kronu', 'unit' => 1],
        'CAD' => ['name' => 'Kanada Doları', 'unit' => 1],
        'KWD' => ['name' => 'Kuveyt Dinarı', 'unit' => 1],
        'NOK' => ['name' => 'Norveç Kronu', 'unit' => 1],
        'SAR' => ['name' => 'Suudi Arabistan Riyali', 'unit' => 1],
        'JPY' => ['name' => 'Japon Yeni', 'unit' => 100],
        'BGN' => ['name' => 'Bulgar Levası', 'unit' => 1],
        'RON' => ['name' => 'Rumen Leyi', 'unit' => 1],
        'RUB' => ['name' => 'Rus Rublesi', 'unit' => 1],
        'IRR' => ['name' => 'İran Riyali', 'unit' => 100],
        'CNY' => ['name' => 'Çin Yuanı', 'unit' => 1],
        'PKR' => ['name' => 'Pakistan Rupisi', 'unit' => 1],
        'QAR' => ['name' => 'Katar Riyali', 'unit' => 1],
        'KRW' => ['name' => 'Güney Kore Wonu', 'unit' => 1],
        'AZN' => ['name' => 'Azerbaycan Yeni Manatı', 'unit' => 1],
        'AED' => ['name' => 'Birleşik Arap Emirlikleri Dirhemi', 'unit' => 1],
    ];

    /** @var array<string, string> */
    private const SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CHF' => 'CHF', 'JPY' => '¥', 'CNY' => '¥',
        'RUB' => '₽', 'KRW' => '₩', 'AZN' => '₼', 'AUD' => 'A$', 'CAD' => 'C$',
    ];

    public function __construct(
        private readonly CurrencyManager $currencies,
        private readonly CurrencyService $currencyService,
    ) {}

    /**
     * TCMB'nin yayımladığı para birimleri; panelde eklenmiş olanlar hariç.
     * Silinmiş (soft delete) olanlar `restorable` ile işaretlenir.
     *
     * @return array<int, array{code: string, name: string, unit: int, restorable: bool}>
     */
    public function availableTcmbCurrencies(): array
    {
        $list = $this->fetchTcmbCurrencyList();
        $existing = Currency::query()->withTrashed()->get(['id', 'code', 'deleted_at']);
        $active = $existing->whereNull('deleted_at')->pluck('code')->all();
        $trashed = $existing->whereNotNull('deleted_at')->pluck('code')->all();

        return collect($list)
            ->reject(fn (array $c, string $code) => in_array($code, $active, true))
            ->map(fn (array $c, string $code) => [
                'code' => $code,
                'name' => $c['name'],
                'unit' => $c['unit'],
                'restorable' => in_array($code, $trashed, true),
            ])
            ->sortBy('code')
            ->values()
            ->all();
    }

    /**
     * TCMB listesindeki bir para birimini ekler (ya da silinmişse geri alır),
     * kaynağını TCMB yapar ve kurunu hemen çeker.
     */
    public function addTcmbCurrency(string $code, User $causer): Currency
    {
        $code = strtoupper($code);
        $list = $this->fetchTcmbCurrencyList();

        if (! isset($list[$code])) {
            throw new ShopException(__(':code TCMB listesinde yok.', ['code' => $code]));
        }

        $currency = DB::transaction(function () use ($code, $list, $causer) {
            $existing = Currency::query()->withTrashed()->where('code', $code)->first();

            if ($existing !== null && ! $existing->trashed()) {
                throw new ShopException(__(':code zaten ekli.', ['code' => $code]));
            }

            if ($existing !== null) {
                $this->currencyService->restore($existing, $causer);
                $existing->update(['rate_source' => RateSource::Tcmb, 'status' => Status::Active]);

                return $existing;
            }

            return $this->currencyService->create([
                'code' => $code,
                'name' => $list[$code]['name'],
                'symbol' => self::SYMBOLS[$code] ?? $code,
                'decimal_places' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
                'exchange_rate' => 1,
                'rate_source' => RateSource::Tcmb,
                'status' => Status::Active,
                'sort_order' => (int) Currency::query()->max('sort_order') + 1,
            ], $causer);
        });

        try {
            $rates = $this->fetchTcmb();
        } catch (ShopException) {
            return $currency;
        }

        if (isset($rates[$code])) {
            $currency->update(['exchange_rate' => $rates[$code], 'rate_updated_at' => now()]);
            ExchangeRatesUpdatedEvent::dispatch([$code => $rates[$code]], 'tcmb', $causer);
        }

        return $currency;
    }

    /**
     * TCMB XML'inden kod => ad / birim listesi; ulaşılamazsa sabit liste.
     *
     * @return array<string, array{name: string, unit: int}>
     */
    public function fetchTcmbCurrencyList(): array
    {
        try {
            $response = Http::timeout(10)->get(self::TCMB_URL);
        } catch (Throwable) {
            return self::TCMB_CURRENCIES;
        }

        if (! $response->successful()) {
            return self::TCMB_CURRENCIES;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($response->body());
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            return self::TCMB_CURRENCIES;
        }

        $list = [];

        foreach ($doc->Currency as $node) {
            $code = (string) $node['CurrencyCode'];

            if ($code === '' || $code === 'XDR') {
                continue;
            }

            $list[$code] = [
                'name' => trim((string) $node->Isim) ?: (self::TCMB_CURRENCIES[$code]['name'] ?? trim((string) $node->CurrencyName)),
                'unit' => (int) ((string) $node->Unit ?: 1),
            ];
        }

        return $list !== [] ? $list : self::TCMB_CURRENCIES;
    }

    /**
     * TCMB'den kurları çeker ve `tcmb` kaynaklı para birimlerini günceller.
     *
     * @return array<string, BigDecimal> güncellenen kod => kur
     */
    public function syncFromTcmb(?User $causer = null): array
    {
        $rates = $this->fetchTcmb();
        $updated = [];

        DB::transaction(function () use ($rates, &$updated) {
            // Kurlar TL karşılığıdır: TL dışındaki ana para birimi (ör. USD) de TCMB'den
            // güncellenir (Money::baseRate onun TL değerini kullanır).
            $targets = Currency::query()
                ->where('rate_source', RateSource::Tcmb->value)
                ->where('code', '!=', 'TRY')
                ->get();

            foreach ($targets as $currency) {
                if (! isset($rates[$currency->code])) {
                    continue;
                }

                $currency->update([
                    'exchange_rate' => $rates[$currency->code],
                    'rate_updated_at' => now(),
                ]);

                $updated[$currency->code] = $rates[$currency->code];
            }
        });

        if ($updated !== []) {
            ExchangeRatesUpdatedEvent::dispatch($updated, 'tcmb', $causer);
        }

        return $updated;
    }

    /**
     * Elle kur girişi.
     *
     * @param  array<string, array{exchange_rate?: float|string|null, rate_source?: string|null}>  $rows  currency_id => değerler
     */
    public function saveManual(array $rows, User $causer): void
    {
        $changed = [];

        DB::transaction(function () use ($rows, &$changed) {
            foreach ($rows as $id => $row) {
                $currency = Currency::query()->find($id);

                if ($currency === null) {
                    continue;
                }

                // TL'nin kuru her zaman 1'dir (kurlar TL karşılığı). TL dışındaki ana para
                // biriminin kuru ise TL değeridir; 1'e çekilmez (1 USD = 1 TL olurdu).
                if ($currency->code === 'TRY') {
                    $currency->update(['exchange_rate' => 1, 'rate_source' => RateSource::Manual]);

                    continue;
                }

                $data = [];

                if (isset($row['rate_source'])) {
                    $data['rate_source'] = RateSource::from($row['rate_source']);
                }

                if (isset($row['exchange_rate']) && $row['exchange_rate'] !== '') {
                    $rate = Money::round(str_replace(',', '.', (string) $row['exchange_rate']), 6);

                    if ($rate->isPositive() && ! $rate->isEqualTo($currency->exchange_rate ?? 0)) {
                        $data['exchange_rate'] = $rate;
                        $data['rate_updated_at'] = now();
                        $changed[$currency->code] = $rate;

                        // Kaynak belirtilmeden TCMB kaynaklı kura elle değer girildiyse kaynak
                        // "elle" olur (sonraki senkronizasyon girilen kuru sessizce ezmesin).
                        // Kaynak açıkça "TCMB" seçildiyse girilen değer ilk senkronizasyona kadar geçerlidir.
                        if (! isset($row['rate_source']) && $currency->rate_source === RateSource::Tcmb) {
                            $data['rate_source'] = RateSource::Manual;
                        }
                    }
                }

                if ($data !== []) {
                    $currency->update($data);
                }
            }
        });

        if ($changed !== []) {
            ExchangeRatesUpdatedEvent::dispatch($changed, 'manual', $causer);
        }
    }

    /**
     * @return array<string, BigDecimal> kod => 1 birimin TL karşılığı
     */
    public function fetchTcmb(): array
    {
        try {
            $response = Http::timeout(15)->retry(2, 500)->get(self::TCMB_URL);
        } catch (Throwable $e) {
            throw new ShopException(__('TCMB kurlarına ulaşılamadı: :error', ['error' => $e->getMessage()]));
        }

        if (! $response->successful()) {
            throw new ShopException(__('TCMB kurlarına ulaşılamadı (HTTP :status).', ['status' => $response->status()]));
        }

        return $this->parseTcmbXml($response->body());
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function parseTcmbXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new ShopException(__('TCMB kur verisi okunamadı.'));
        }

        $field = ShopSettings::get('tcmb_rate_type') ?: 'ForexSelling';
        $rates = [];

        foreach ($doc->Currency as $node) {
            $code = (string) $node['CurrencyCode'];
            $unit = self::parse((string) $node->Unit ?: '1');
            $value = self::parse((string) $node->{$field});

            if (! $value->isPositive()) {
                $value = self::parse((string) $node->ForexSelling);
            }

            if ($code !== '' && $value->isPositive() && $unit->isPositive()) {
                $rates[$code] = Money::div($value, $unit, 6);
            }
        }

        return $rates;
    }

    /**
     * TCMB sayısı ("1,2345" / "34.5678" / boş) → kesin ondalık; geçersizse sıfır.
     */
    private static function parse(string $value): BigDecimal
    {
        $value = trim(str_replace(',', '.', $value));

        return is_numeric($value) ? Money::decimal($value) : BigDecimal::zero();
    }
}
