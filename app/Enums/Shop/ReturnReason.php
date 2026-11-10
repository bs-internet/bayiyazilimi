<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum ReturnReason: string
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => __('Hasarlı / kusurlu ürün'),
            self::WrongItem => __('Yanlış ürün gönderildi'),
            self::ChangedMind => __('Vazgeçtim'),
            self::Other => __('Diğer'),
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
