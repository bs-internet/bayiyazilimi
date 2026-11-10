<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use Illuminate\Http\JsonResponse;

/**
 * İl / ilçe seçimleri (Tanımlamalar → Bölgeler verisi). Alan bağımsızdır:
 * panel, bayi alanı ve (kuruluysa) vitrin aynı uçları kullanır.
 */
class LocationController extends Controller
{
    public function countryCities(Country $country): JsonResponse
    {
        return response()->json(
            City::query()
                ->where('country_id', $country->id)
                ->where('status', Status::Active->value)
                ->orderBy('code')
                ->get(['id', 'name']),
        );
    }

    public function districts(City $city): JsonResponse
    {
        return response()->json(
            District::query()
                ->where('city_id', $city->id)
                ->where('status', Status::Active->value)
                ->orderBy('name')
                ->get(['id', 'name']),
        );
    }
}
