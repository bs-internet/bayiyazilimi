import { SectionCard } from '@/components/admin/page';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

import type { QuoteHistory } from './types';

/** Teklifin adım adım geçmişi (müşteri notları dahil). */
export function HistoryCard({ histories }: { histories: QuoteHistory[] }) {
    return (
        <SectionCard title={t('Geçmiş')}>
            {histories.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('Kayıt yok.')}
                </p>
            ) : (
                <ol className="flex flex-col gap-4">
                    {histories.map((h) => (
                        <li key={h.id} className="relative pl-5">
                            <span
                                className={cn(
                                    'absolute top-1.5 left-0 size-2 rounded-full',
                                    h.by_customer ? 'bg-chart-3' : 'bg-primary',
                                )}
                            />
                            <div className="text-sm font-medium">{h.label}</div>
                            <div className="text-xs text-muted-foreground">
                                {formatDateTime(h.created_at)}
                                {h.user ? ` · ${h.user}` : ''}
                            </div>
                            {h.note ? (
                                <p className="mt-1 rounded-md bg-muted px-2.5 py-1.5 text-sm whitespace-pre-line">
                                    {h.note}
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ol>
            )}
        </SectionCard>
    );
}
