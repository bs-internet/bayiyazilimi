<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Mal kabulün dayandığı tedarikçi belgesi.
 */
enum ReceiptDocumentType: string
{
    case Waybill = 'waybill';
    case Invoice = 'invoice';
    case WaybillInvoice = 'waybill_invoice';

    public function label(): string
    {
        return match ($this) {
            self::Waybill => __('İrsaliye'),
            self::Invoice => __('Fatura'),
            self::WaybillInvoice => __('İrsaliyeli Fatura'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
