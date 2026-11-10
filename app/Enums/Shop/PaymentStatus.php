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
