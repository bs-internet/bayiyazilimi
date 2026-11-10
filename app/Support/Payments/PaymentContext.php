<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;

/**
 * Ödeme yönteminin sunulup sunulmayacağına karar verilen bağlam: kim, ne kadar,
 * hangi para biriminde, ne için (sipariş / cari tahsilat).
 */
final readonly class PaymentContext
{
    public const PURPOSE_ORDER = 'order';

    public const PURPOSE_BALANCE = 'balance';

    public function __construct(
        public ?User $user,
        public ?Account $account,
        public BigDecimal $total,
        public ?Currency $currency,
        public string $purpose = self::PURPOSE_ORDER,
    ) {}

    public static function for(?User $user, BigNumber|int|string $total = 0, ?Currency $currency = null, string $purpose = self::PURPOSE_ORDER): self
    {
        $account = $user?->account_id !== null ? $user->account : null;

        return new self($user, $account, Money::round($total), $currency, $purpose);
    }

    public function currencyCode(): string
    {
        return $this->currency?->code ?? 'TRY';
    }
}
