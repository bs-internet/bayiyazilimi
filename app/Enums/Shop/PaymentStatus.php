<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Bekliyor'),
            self::Partial => __('Kısmen Ödendi'),
            self::Paid => __('Ödendi'),
            self::Failed => __('Başarısız'),
            self::Refunded => __('İade Edildi'),
            self::Cancelled => __('İptal Edildi'),
        };
    }

    /**
     * Siparişin ödeme durumu geçiş haritası (`orders.payment_status`). Durumu
     * yazan her yol (OrderService::markPaid / markPaymentFailed / releaseReservation /
     * updatePaymentStatus, LedgerService::reallocate, PaymentRefundService,
     * ReturnService) yazmadan önce `assertTransition()` ile bu haritaya bakar;
     * her yolun kendi iş kuralı (ör. "yalnız bekleyen sipariş başarısız olur")
     * ayrıca geçerlidir. `Cancelled` yalnız ödeme (tahsilat) kayıtlarındadır.
     *
     * - Bekliyor → Kısmen / Ödendi (tahsilat, sağlayıcı, yönetici), Başarısız (sağlayıcı, süre, yönetici)
     * - Kısmen → Ödendi, Bekliyor (tahsilat ters kaydı / yönetici), Başarısız (süre), İade Edildi
     * - Ödendi → Bekliyor / Kısmen (yönetici geri alır, vadeli siparişte tahsilat ters kaydı), İade Edildi
     * - Başarısız → Ödendi (yönetici ya da geç gelen ödeme), Bekliyor (yönetici; sipariş başarısız değilse)
     * - İade Edildi → Ödendi (yönetici yeniden ödendi işaretler)
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Pending => [self::Partial, self::Paid, self::Failed],
            self::Partial => [self::Paid, self::Pending, self::Failed, self::Refunded],
            self::Paid => [self::Pending, self::Partial, self::Refunded],
            self::Failed => [self::Paid, self::Pending],
            self::Refunded => [self::Paid],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /**
     * Haritada olmayan geçiş bir kod hatasıdır (yolun kendi kuralı bunu önlemeliydi).
     */
    public function assertTransition(self $target): void
    {
        if ($this !== $target && ! $this->canTransitionTo($target)) {
            throw new \LogicException(sprintf('Geçersiz ödeme durumu geçişi: %s → %s', $this->value, $target->value));
        }
    }

    /**
     * Yöneticinin sipariş ekranından seçebileceği ödeme durumları ("Kısmen Ödendi"
     * cari dağıtımdan, "İptal Edildi" yalnız tahsilat kayıtlarında kullanılır).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            [self::Pending, self::Paid, self::Failed, self::Refunded],
        );
    }

    /**
     * Filtre listeleri için tüm durumlar.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function allOptions(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
