import { Link } from '@inertiajs/react';

import { show as orderShow } from '@/routes/panel/shop/orders';
import { show as countShow } from '@/routes/panel/shop/stock-counts';
import { show as receiptShow } from '@/routes/panel/shop/stock-receipts';
import type { StockMovementItem } from '@/types';

/**
 * Stok hareketinin kaynağı (sipariş, mal kabul, sayım) bağlantısı. Kaynağın
 * türüne göre doğru ekranı açar; kaynak yoksa hiçbir şey çizmez.
 */
export function MovementReference({
    movement,
}: {
    movement: Pick<
        StockMovementItem,
        'reference' | 'reference_id' | 'reference_kind'
    >;
}) {
    if (!movement.reference || !movement.reference_id) {
        return null;
    }

    const href =
        movement.reference_kind === 'receipt'
            ? receiptShow(movement.reference_id)
            : movement.reference_kind === 'count'
              ? countShow(movement.reference_id)
              : orderShow(movement.reference_id);

    return (
        <Link href={href} className="font-medium hover:underline">
            {movement.reference}
        </Link>
    );
}
