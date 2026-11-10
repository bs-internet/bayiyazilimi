<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Carrier;
use App\Models\CarrierRate;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Desi bazlı kargo ücreti.
 *
 * Her kargo firmasının desi aralığı → fiyat (TL) tablosu vardır. Sepetin toplam
 * desisi (ürün desisi × adet; desisi girilmemiş ürün için varsayılan desi)
 * tabloda bulunur; tablonun üstündeki her desi için firmanın "ek desi ücreti"
 * eklenir. Ücretsiz kargo eşiği ve "ücretsiz kargo" işaretli ürünler 0 TL'dir.
 */
class ShippingService
{
    public function __construct(
        private readonly CurrencyManager $currencies,
    ) {}

    /**
     * @return Collection<int, Carrier>
     */
    public function activeCarriers(): Collection
    {
        return Carrier::query()
            ->where('status', Status::Active->value)
            ->with('rates')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();
    }

    public function defaultCarrier(): ?Carrier
    {
        return $this->activeCarriers()->first();
    }

    /**
     * Sepet satırlarının toplam desisi.
     *
     * @param  iterable<int, array{product: Product, quantity: int}>  $lines
     */
    public function totalDesi(iterable $lines): BigDecimal
    {
        $fallback = Money::max(0, Money::round(ShopSettings::get('default_product_desi')));
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            $desi = $line['product']->desi;
            $total = $total->plus(($desi->isPositive() ? $desi : $fallback)->multipliedBy($line['quantity']));
        }

        return Money::round($total);
    }

    /**
     * Firmanın tablosuna göre KDV dahil TL ücret.
     */
    public function priceFor(Carrier $carrier, BigNumber|int|string $desi): BigDecimal
    {
        $rates = $carrier->relationLoaded('rates') ? $carrier->rates : $carrier->rates()->get();

        if ($rates->isEmpty()) {
            throw new ShopException(__(':name için desi fiyat tablosu tanımlı değil.', ['name' => $carrier->name]));
        }

        $desi = Money::decimal($desi);
        /** @var CarrierRate|null $match */
        $match = $rates->first(fn (CarrierRate $r) => $desi->isLessThanOrEqualTo($r->max_desi) && $desi->isGreaterThanOrEqualTo($r->min_desi))
            ?? $rates->first(fn (CarrierRate $r) => $desi->isLessThanOrEqualTo($r->max_desi));

        if ($match !== null) {
            $price = Money::round($match->price);
        } else {
            /** @var CarrierRate $last */
            $last = $rates->sortBy(fn (CarrierRate $r) => $r->max_desi->toFloat())->last();
            // Tablonun üstündeki her (başlamış) desi için ek ücret.
            $extra = $desi->minus($last->max_desi)->toScale(0, RoundingMode::Ceiling)->toInt();
            $price = Money::add($last->price, Money::times($carrier->extra_desi_price, $extra));
        }

        if (! $carrier->prices_include_tax) {
            $price = Money::withRate($price, 20);
        }

        return $price;
    }

    /**
     * Sepet için kargo ücreti (hedef para biriminde).
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    public function quote(Collection $lines, ?Carrier $carrier, BigNumber|int|string $grossTotal, Currency $currency): BigDecimal
    {
        $normal = $this->normalQuote($lines, $carrier, $grossTotal, $currency);

        // Kampanyanın kargosu (Basket::lines `campaign_shipping`: ücretsiz / sabit).
        // Normal ücret daha düşükse (ör. eşik aşıldı) müşteri lehine o geçerlidir.
        $campaign = $lines->first()['campaign_shipping'] ?? null;

        return $campaign !== null ? Money::min($normal, $campaign) : $normal;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    private function normalQuote(Collection $lines, ?Carrier $carrier, BigNumber|int|string $grossTotal, Currency $currency): BigDecimal
    {
        $free = Money::round(0);

        if ($lines->isEmpty() || $lines->every(fn ($l) => $l['product']->free_shipping)) {
            return $free;
        }

        $base = $this->currencies->base();
        $threshold = Money::round(ShopSettings::get('free_shipping_threshold'));

        if ($threshold->isPositive() && $this->currencies->convert($grossTotal, $currency, $base)->isGreaterThanOrEqualTo($threshold)) {
            return $free;
        }

        if ($carrier === null) {
            $fee = Money::round(ShopSettings::get('shipping_fee'));

            return $fee->isPositive() ? Money::round($this->currencies->convert($fee, $base, $currency)) : $free;
        }

        $paid = $lines->reject(fn ($l) => $l['product']->free_shipping);
        $priceTry = $this->priceFor($carrier, $this->totalDesi($paid));

        return Money::round($this->currencies->convert($priceTry, $base, $currency));
    }

    /**
     * Desi tablosunu elle kaydeder (tamamen değiştirir).
     *
     * @param  array<int, array{min_desi: float|string, max_desi: float|string, price: float|string}>  $rows
     */
    public function saveRates(Carrier $carrier, array $rows, User $causer): int
    {
        $clean = $this->normalizeRows($rows);

        DB::transaction(function () use ($carrier, $clean) {
            $carrier->rates()->delete();

            foreach ($clean as $row) {
                $carrier->rates()->create($row);
            }
        });

        CatalogChangedEvent::dispatch($carrier, 'carrier', 'rates', __(':name desi fiyat tablosunu güncelledi (:count satır).', ['name' => $carrier->name, 'count' => count($clean)]), $causer);

        return count($clean);
    }

    /**
     * Kargo firmasının Excel'inden desi tablosunu yükler.
     *
     * Kabul edilen biçimler (ilk satır başlık):
     * - "Desi Başlangıç | Desi Bitiş | Fiyat"
     * - "Desi | Fiyat" (her satır bir üst sınır; alt sınır önceki satırın üstü)
     */
    public function importRates(Carrier $carrier, string $xlsxPath, User $causer): int
    {
        $reader = new Reader;
        $reader->open($xlsxPath);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $first = true;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_values(array_filter(
                        array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row->toArray()),
                        fn ($v) => $v !== null && $v !== '',
                    ));

                    if ($first) {
                        $first = false;

                        // Başlık satırı değilse (sayısal) veri olarak işle.
                        if (! is_numeric($this->num($values[0] ?? null))) {
                            continue;
                        }
                    }

                    if (count($values) >= 3) {
                        $rows[] = ['min_desi' => $values[0], 'max_desi' => $values[1], 'price' => $values[2]];
                    } elseif (count($values) === 2) {
                        $rows[] = ['min_desi' => null, 'max_desi' => $values[0], 'price' => $values[1]];
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($rows === []) {
            throw new ShopException(__('Excel dosyasında desi/fiyat satırı bulunamadı.'));
        }

        return $this->saveRates($carrier, $rows, $causer);
    }

    /**
     * Boş desi şablonu (xlsx) üretir ve dosya yolunu döner.
     */
    public function template(?Carrier $carrier = null): string
    {
        $path = storage_path('app/private/desi-sablonu-'.uniqid().'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([__('Desi Başlangıç'), __('Desi Bitiş'), __('Fiyat (TL)')]));

        $rates = $carrier?->rates()->get() ?? collect();

        if ($rates->isEmpty()) {
            foreach ([[0, 1, 0], [1, 2, 0], [2, 3, 0], [3, 5, 0], [5, 10, 0], [10, 15, 0], [15, 20, 0], [20, 30, 0]] as $r) {
                $writer->addRow(Row::fromValues($r));
            }
        } else {
            foreach ($rates as $rate) {
                $writer->addRow(Row::fromValues([Money::toFloat($rate->min_desi), Money::toFloat($rate->max_desi), Money::toFloat($rate->price)]));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{min_desi: float, max_desi: float, price: float}>
     */
    private function normalizeRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $max = $this->num($row['max_desi'] ?? null);
            $price = $this->num($row['price'] ?? null);

            if ($max === null || $price === null) {
                continue;
            }

            $min = $this->num($row['min_desi'] ?? null);
            $clean[] = ['min_desi' => $min, 'max_desi' => $max, 'price' => $price];
        }

        usort($clean, fn ($a, $b) => $a['max_desi'] <=> $b['max_desi']);

        $previous = 0.0;
        foreach ($clean as $i => $row) {
            $clean[$i]['min_desi'] = $row['min_desi'] ?? $previous;

            if ($clean[$i]['min_desi'] > $row['max_desi']) {
                throw new ShopException(__('Desi aralığı hatalı: :min_desi - :max_desi.', ['min_desi' => $clean[$i]['min_desi'], 'max_desi' => $row['max_desi']]));
            }

            $previous = $row['max_desi'];
        }

        return $clean;
    }

    private function num(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace(['TL', '₺', ' '], '', (string) $value);
        // 1.250,50 → 1250.50
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
