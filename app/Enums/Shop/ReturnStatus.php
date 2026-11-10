<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum ReturnStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Beklemede'),
            self::Approved => __('Onaylandı'),
            self::Rejected => __('Reddedildi'),
            self::Received => __('Ürün Teslim Alındı'),
            self::Refunded => __('İade Tamamlandı'),
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Rejected || $this === self::Refunded;
    }

    /**
     * İzinli geçişler: ürün teslim alınmadan iade tamamlanmaz. Teslim alınan
     * talebin reddi, ürün stoğa alınmamışsa mümkündür (ReturnService denetler).
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Received, self::Rejected],
            self::Received => [self::Refunded, self::Rejected],
            self::Rejected, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /**
     * Yönetici seçicisinin seçenekleri: mevcut durum (yalnız not güncellemek için) + izinli hedefler.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function targetOptions(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], [$this, ...$this->allowedTargets()]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
