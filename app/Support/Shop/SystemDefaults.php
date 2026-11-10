<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Country;
use App\Models\Setting;

/**
 * Genel Ayarlar → "Varsayılanlar" değerlerinin mağazadaki karşılıkları.
 */
final class SystemDefaults
{
    /**
     * Varsayılan ülke (il/ilçe listeleri ve adresler için); ayarlanmamışsa Türkiye.
     */
    public static function countryId(): ?string
    {
        $id = Setting::get('default_country_id');

        if ($id !== null && $id !== '' && Country::query()->whereKey($id)->exists()) {
            return $id;
        }

        return Country::query()->where('code', 'TR')->value('id');
    }
}
