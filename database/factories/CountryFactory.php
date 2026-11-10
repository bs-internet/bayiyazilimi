<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            // Rastgele gerçek ülke adı (ör. "Türkiye") kurulu kayıtlarla slug çakıştırabilir.
            'name' => fake()->unique()->lexify('Test Ülkesi ??????'),
            'status' => Status::Active,
            'sort_order' => 0,
        ];
    }
}
