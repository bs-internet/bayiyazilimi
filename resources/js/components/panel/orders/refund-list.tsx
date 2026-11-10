import { Check, X } from 'lucide-react';

import { ConfirmDelete } from '@/components/confirm-delete';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { abandon, confirm } from '@/routes/panel/shop/payments/refunds';

import type { OrderPaymentRefund } from './types';

const STATUS_CLASS: Record<OrderPaymentRefund['status'], string> = {
    pending: 'text-amber-600 dark:text-amber-400',
    succeeded: 'text-muted-foreground',
    failed: 'text-destructive',
};

/**
 * Ödemenin sağlayıcı iadeleri. Sağlayıcı çağrısından sonra yarıda kalan
 * ("İşlemde") iade, yönetici sağlayıcı panelinden kontrol edip burada
 * "Tamamlandı" ya da "Yapılmadı" olarak sonuçlandırır.
 */
export function RefundList({
    refunds,
    canResolve,
}: {
    refunds: OrderPaymentRefund[];
    canResolve: boolean;
}) {
    return (
        <ul className="mt-2 flex flex-col items-end gap-1 text-xs font-normal">
            {refunds.map((r) => (
                <li key={r.id} className="flex flex-col items-end gap-1">
                    <span className="whitespace-nowrap">
                        {t('İade :amount_formatted · :date', {
                            amount_formatted: r.amount_formatted,
                            date: formatDateTime(r.created_at),
                        })}{' '}
                        <span
                            className={cn(
                                'font-medium',
                                STATUS_CLASS[r.status],
                            )}
                        >
                            {r.status_label}
                        </span>
                    </span>
                    {r.failed_reason && (
                        <span className="max-w-56 whitespace-normal text-destructive">
                            {r.failed_reason}
                        </span>
                    )}
                    {r.status === 'pending' && !r.resolvable && (
                        <span className="text-muted-foreground">
                            {t('Sağlayıcıda işleniyor…')}
                        </span>
                    )}
                    {r.status === 'pending' && r.resolvable && canResolve && (
                        <span className="flex gap-1">
                            <ConfirmDelete
                                action={confirm(r.id)}
                                title={t('İade sağlayıcıda yapıldı mı?')}
                                description={t(
                                    'Sağlayıcı panelinde bu iadenin başarıyla yapıldığını gördüyseniz onaylayın. İade tamamlanır; cari, ödeme ve sipariş durumu buna göre güncellenir.',
                                )}
                                confirmLabel={t('İade yapıldı')}
                                confirmIcon={Check}
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 px-2 text-xs"
                                >
                                    <Check data-icon="inline-start" />
                                    {t('Yapıldı')}
                                </Button>
                            </ConfirmDelete>
                            <ConfirmDelete
                                action={abandon(r.id)}
                                title={t('İade sağlayıcıda yapılmadı mı?')}
                                description={t(
                                    'Sağlayıcı panelinde bu iade görünmüyorsa onaylayın. Kayıt "Başarısız" olur, cariye dokunulmaz ve tutar yeniden iade edilebilir.',
                                )}
                                confirmLabel={t('İade yapılmadı')}
                                confirmIcon={X}
                            >
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="h-7 px-2 text-xs"
                                >
                                    <X data-icon="inline-start" />
                                    {t('Yapılmadı')}
                                </Button>
                            </ConfirmDelete>
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}
