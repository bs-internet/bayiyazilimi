import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { LedgerOpenOrder } from '@/types';

/**
 * Tahsilatın kapatacağı siparişler (isteğe bağlı). Seçilenler önce kapanır,
 * hiç seçilmezse tahsilat en eski vadeli borçlara dağılır.
 */
export function OpenOrderPicker({
    orders,
    error,
}: {
    orders: LedgerOpenOrder[];
    error?: string;
}) {
    if (orders.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <Label>{t('Siparişler (isteğe bağlı)')}</Label>
            <div className="max-h-44 divide-y overflow-y-auto rounded-md border">
                {orders.map((order) => (
                    <label
                        key={order.id}
                        className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm"
                    >
                        <Checkbox name="order_ids[]" value={order.id} />
                        <span className="flex-1 font-medium">
                            {order.number}
                        </span>
                        {order.due_date && (
                            <span className="text-xs text-muted-foreground">
                                {t('Vade: :date', { date: order.due_date })}
                            </span>
                        )}
                        <span className="tabular-nums">{order.open}</span>
                    </label>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                {t(
                    'Seçilen siparişler önce kapanır; artan tutar ya da seçim yapılmazsa tamamı en eski vadeli borçlara gider.',
                )}
            </p>
            <InputError message={error} />
        </div>
    );
}
