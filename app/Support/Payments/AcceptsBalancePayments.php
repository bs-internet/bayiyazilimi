<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Account;
use App\Models\User;
use Brick\Math\BigDecimal;

/**
 * Siparişten bağımsız cari tahsilat (bayi bakiyesine ödeme, ör. kartla).
 * Sürücü bekleyen bir `payments` satırı açar (order_id boş) ve ödeme sayfasına yönlendirir.
 */
interface AcceptsBalancePayments
{
    /**
     * @param  array<int, string>  $orderIds  ödemenin önce kapatacağı siparişler (boşsa en eski vadeli borçlar)
     */
    public function startBalancePayment(Account $account, User $user, BigDecimal $amount, array $orderIds = []): PaymentStart;
}
