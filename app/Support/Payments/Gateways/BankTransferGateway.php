<?php

declare(strict_types=1);

namespace App\Support\Payments\Gateways;

use App\Models\BankAccount;
use App\Models\Order;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use App\Support\Payments\BaseGateway;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentStart;
use Brick\Math\BigDecimal;

/**
 * Havale / EFT. Banka Hesapları ekranında açık ve en az bir aktif hesap varsa
 * sunulur. Sipariş "Ödeme Bekleniyor" kalır; yönetici ödemeyi onaylayınca
 * (ya da müşterinin ödeme bildirimi onaylanınca) ödeme kaydı yazılır.
 */
final class BankTransferGateway extends BaseGateway
{
    public const KEY = 'bank_transfer';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Havale / EFT');
    }

    public function description(PaymentContext $context): ?string
    {
        return __('Banka hesap bilgilerimiz sipariş onay sayfasında ve e-postanızda yer alır. Açıklamaya sipariş numaranızı yazmayı unutmayın.');
    }

    public function icon(): string
    {
        return 'landmark';
    }

    public function available(PaymentContext $context): bool
    {
        return ShopSettings::bool('bank_transfer_enabled') && BankAccount::query()->active()->exists();
    }

    /**
     * Havale indirimi (Banka Hesapları ekranı, %): yalnız sipariş ödemesinde.
     */
    public function adjustmentRate(PaymentContext $context): BigDecimal
    {
        $rate = Money::round(ShopSettings::get('bank_transfer_discount_rate'));

        return $context->purpose === PaymentContext::PURPOSE_ORDER && $rate->isPositive() ? Money::min($rate, 100)->negated() : Money::round(0);
    }

    public function adjustmentLabel(PaymentContext $context): ?string
    {
        $rate = $this->adjustmentRate($context);

        return $rate->isNegative() ? __('Havale indirimi (%:rate)', ['rate' => rtrim(rtrim(Money::number($rate->abs()), '0'), ',')]) : null;
    }

    public function start(Order $order): PaymentStart
    {
        return PaymentStart::none();
    }
}
