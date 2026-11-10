<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Üye hesabının türü: panelden eklenen bayi ya da vitrinden kayıt olan müşteri.
 * Bayi `/app` üye alanını, müşteri vitrindeki "Hesabım" ekranlarını kullanır.
 *
 * Çekirdekte (B2B) seçilebilen tek tür bayidir; müşteri türünü vitrin modülü
 * `shop.customer_types` filtresiyle açar (bkz. available()).
 */
enum CustomerType: string
{
    case Customer = 'customer';
    case Dealer = 'dealer';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('Müşteri'),
            self::Dealer => __('Bayi'),
        };
    }

    /**
     * Bu kurulumda seçilebilen türler.
     *
     * @return array<int, self>
     */
    public static function available(): array
    {
        /** @var array<int, self> $types */
        $types = hooks()->apply('shop.customer_types', [self::Dealer]);

        return array_values(array_unique($types, SORT_REGULAR));
    }

    /**
     * Müşteri (B2C) hesapları bu kurulumda var mı? Çekirdekte hayır; vitrin
     * modülü açar. Müşteriye özgü düğme, etiket ve kırılımlar buna bakar.
     */
    public static function customersEnabled(): bool
    {
        return in_array(self::Customer, self::available(), true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::available());
    }
}
