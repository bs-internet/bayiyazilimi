<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Enums\Status;
use App\Models\City;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;
use App\Support\Shop\SystemDefaults;

/**
 * Teslimat ili. İl ödeme adımında (teslimat adresi) kesinleşir; bayide
 * varsayılan teslimat adresinin ili kullanılır. İl bilinmiyorsa koşul
 * sağlanmamış sayılır (müşteriye gösterilmeyen indirim siparişte çıkmasın diye
 * tersi değil: bilinmeyen il indirimi kapatır, siparişte açabilir). Ürün kartı
 * etiketinde sağlanmış sayılır.
 */
final class CityCondition extends Condition
{
    public function key(): string
    {
        return 'city';
    }

    public function label(): string
    {
        return __('Teslimat ili');
    }

    public function scope(): string
    {
        return self::AUDIENCE;
    }

    public function operators(): array
    {
        return Operators::membership();
    }

    public function input(): string
    {
        return 'multi';
    }

    public function options(): array
    {
        $country = SystemDefaults::countryId();

        // Değer il adıdır (adres formu ili adıyla saklar).
        return City::query()
            ->when($country, fn ($q) => $q->where('country_id', $country))
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => ['value' => $name, 'label' => $name])
            ->all();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        if ($context->preview) {
            return true;
        }

        if ($context->city === null || trim($context->city) === '') {
            return false;
        }

        $city = mb_strtolower(trim($context->city), 'UTF-8');
        $list = array_map(fn ($v) => mb_strtolower(trim((string) $v), 'UTF-8'), (array) $value);

        return Operators::within($city, $operator, $list);
    }
}
