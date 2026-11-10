<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Ürün sorgusuna çevrilebilen "Hangi ürünlere" koşulu. Vitrinin "Kampanyalı
 * Ürünler" listesi kampanyanın kapsadığı ürünleri bununla seçer; bu arayüzü
 * uygulamayan bir koşul içeren kampanya o listeye katılmaz (sepette yine çalışır).
 */
interface ConstrainsProducts
{
    /**
     * `products` tablosu sorgusunu koşula göre daraltır.
     */
    public function constrain(Builder $products, string $operator, mixed $value): void;
}
