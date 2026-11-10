<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ürünün kendi kimliği (müşterinin markasından bağımsız): sürüm. Panel
 * altbilgisinde gösterilir; modüllerin `requires.bayiyazilimi` kısıtı da bu
 * sürüme göredir. Sürüm yükseltilirken burası güncellenir.
 */
final class BayiYazilimi
{
    public const VERSION = '1.0.0';
}
