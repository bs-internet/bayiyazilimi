<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum PaymentNotificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Onay bekliyor'),
            self::Approved => __('Onaylandı'),
            self::Rejected => __('Reddedildi'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
