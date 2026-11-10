<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Services\Shop\ShopSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Rapor filtreleri: tarih aralığı (hazır dönem ya da özel) ve raporun
 * desteklediği ek filtreler. Ekranla Excel aynı filtreyle çalışır.
 */
final readonly class ReportFilters
{
    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'today' => __('Bugün'),
            '7d' => __('Son 7 gün'),
            '30d' => __('Son 30 gün'),
            'this_month' => __('Bu ay'),
            'last_month' => __('Geçen ay'),
            'this_year' => __('Bu yıl'),
            'custom' => __('Özel aralık'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function customerTypeLabels(): array
    {
        return [
            'customer' => __('Bireysel müşteri'),
            'dealer' => __('Bayi'),
            'guest' => __('Misafir'),
        ];
    }

    /**
     * Bu kurulumda süzülebilen müşteri tipleri: çekirdekte yalnız bayi; vitrin
     * modülü bireysel müşteri ve misafiri ekler (`shop.report_customer_types`).
     *
     * @return array<string, string>
     */
    public static function customerTypes(): array
    {
        return array_intersect_key(
            self::customerTypeLabels(),
            array_flip(hooks()->apply('shop.report_customer_types', ['dealer'])),
        );
    }

    /** Tarih aralığı en çok bu kadar gün olabilir (sorgu yükü). */
    private const MAX_DAYS = 1100;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $preset = '30d',
        public ?string $customerType = null,
        public ?string $dealerGroup = null,
        public ?string $warehouse = null,
        public ?string $category = null,
        public ?string $brand = null,
        public ?string $groupBy = null,
        public ?string $stockState = null,
        public int $threshold = 5,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $preset = (string) $request->query('preset', '');
        $from = self::date($request->query('from'));
        $to = self::date($request->query('to'));

        if ($preset !== 'custom' || $from === null || $to === null) {
            $preset = array_key_exists($preset, self::presets()) && $preset !== 'custom' ? $preset : '30d';
            [$from, $to] = self::presetRange($preset);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS);
        }

        $customerType = (string) $request->query('customer_type', '');

        return new self(
            from: $from->startOfDay(),
            to: $to->endOfDay(),
            preset: $preset,
            customerType: array_key_exists($customerType, self::customerTypeLabels()) ? $customerType : null,
            dealerGroup: self::string($request->query('dealer_group')),
            warehouse: self::string($request->query('warehouse')),
            category: self::string($request->query('category')),
            brand: self::string($request->query('brand')),
            groupBy: self::string($request->query('group_by')),
            stockState: self::string($request->query('stock_state')),
            // Ürüne özel kritik stok eşiği olmayanlar için; varsayılanı Stok Ayarları'ndaki genel eşik.
            threshold: max(0, min(100000, (int) $request->query('threshold', (string) ShopSettings::int('low_stock_threshold')))),
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function presetRange(string $preset): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today],
            default => [$today->subDays(29), $today],
        };
    }

    /** Dönemdeki gün sayısı (iki uç dahil). */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Hemen önceki aynı uzunlukta dönem (değişim yüzdeleri için).
     */
    public function previous(): self
    {
        $days = $this->days();

        return new self(
            from: $this->from->subDays($days),
            to: $this->from->subSecond(),
            preset: 'custom',
            customerType: $this->customerType,
            dealerGroup: $this->dealerGroup,
            warehouse: $this->warehouse,
            category: $this->category,
            brand: $this->brand,
            groupBy: $this->groupBy,
            stockState: $this->stockState,
            threshold: $this->threshold,
        );
    }

    /**
     * Ekrana geri gönderilen değerler (formu doldurmak için).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'customer_type' => $this->customerType ?? '',
            'dealer_group' => $this->dealerGroup ?? '',
            'warehouse' => $this->warehouse ?? '',
            'category' => $this->category ?? '',
            'brand' => $this->brand ?? '',
            'group_by' => $this->groupBy ?? '',
            'stock_state' => $this->stockState ?? '',
            'threshold' => (string) $this->threshold,
        ];
    }

    public function periodLabel(): string
    {
        return $this->from->format('d.m.Y').' – '.$this->to->format('d.m.Y');
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 64) : null;
    }
}
