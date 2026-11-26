<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Order;

/**
 * Sipariş önizlemesinin transaction'ı geri almak için fırlattığı iç sinyal
 * (OrderService::previewFromCart dışına çıkmaz).
 *
 * @internal
 */
final class OrderPreview extends \RuntimeException
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct('Sipariş önizlemesi.');
    }
}
