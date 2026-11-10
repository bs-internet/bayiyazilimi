import { SectionCard } from '@/components/admin/page';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';

import { t } from '@/lib/i18n';
import type { OrderDetail } from './types';

/** Sipariş durum geçmişi (zaman çizelgesi). */
export function OrderHistoryCard({
    histories,
}: {
    histories: OrderDetail['histories'];
}) {
    return (
        <SectionCard title={t('Geçmiş')}>
            {histories.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('Kayıt yok.')}
                </p>
            ) : (
                <ol className="relative flex flex-col gap-5 border-l pl-5 text-sm">
                    {histories.map((h) => (
                        <li key={h.id} className="relative">
                            <span
                                aria-hidden
                                className="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full border-2 border-background bg-primary"
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {h.status_label ?? '—'}
                                </span>
                                {h.notify_customer && (
                                    <Badge variant="outline">
                                        {t('Müşteriye bildirildi')}
                                    </Badge>
                                )}
                            </div>
                            <div className="text-xs text-muted-foreground tabular-nums">
                                {formatDateTime(h.created_at)}
                                {' · '}
                                {h.user ?? t('Sistem')}
                            </div>
                            {h.note && (
                                <p className="mt-1 text-muted-foreground">
                                    {h.note}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </SectionCard>
    );
}
