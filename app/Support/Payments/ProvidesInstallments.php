<?php

declare(strict_types=1);

namespace App\Support\Payments;

use Brick\Math\BigDecimal;

/**
 * Taksit tablosu ve BIN sorgusu (kart sağlayıcıları). Ödeme ekranında bilgi
 * amaçlı gösterilir; taksit seçimi sağlayıcının güvenli ödeme sayfasında yapılır.
 */
interface ProvidesInstallments
{
    /**
     * @return array{card: ?array{bank: ?string, family: ?string, type: ?string}, rows: array<int, array{family: string, options: array<int, array{count: int, total: BigDecimal, monthly: BigDecimal}>}>}
     */
    public function installments(BigDecimal $amount, string $currency, ?string $bin = null): array;
}
