<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Liste ekranlarının standart sayfa boyutu. Panel ve üye alanındaki tüm
 * sayfalı listeler (ve modüller) bunu kullanır; tek yerden değişir.
 *
 * İstisnalar bilinçlidir: vitrin katalog ızgarası (satır düzenine göre 24)
 * ve il / ilçe tanım listeleri (tek sayfada görünsün diye 100 / 200).
 */
final class Pagination
{
    public const PER_PAGE = 50;
}
