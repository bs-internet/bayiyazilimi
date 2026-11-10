<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Status;
use App\Models\City;
use App\Support\Shop\SystemDefaults;

/**
 * Form select'leri için bölge seçenekleri (prop olarak verilir).
 */
final class RegionOptions
{
    /**
     * Varsayılan ülkenin (Genel Ayarlar) aktif illeri.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function cities(): array
    {
        $country = SystemDefaults::countryId();

        return City::query()
            ->when($country, fn ($q) => $q->where('country_id', $country))
            ->where('status', Status::Active->value)
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn (City $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }
}
