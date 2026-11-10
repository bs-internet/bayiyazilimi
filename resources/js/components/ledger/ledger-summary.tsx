import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { LedgerData, LedgerDue, LedgerRow } from '@/types';

import { BalanceText } from './ledger-statement';

function Tile({
    label,
    children,
    hint,
    tone,
}: {
    label: string;
    children: ReactNode;
    hint?: ReactNode;
    tone?: 'danger';
}) {
    return (
        <div className="flex flex-col gap-0.5 bg-card px-5 py-4">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'text-lg font-semibold tabular-nums',
                    tone === 'danger' && 'text-destructive',
                )}
            >
                {children}
            </span>
            {hint && (
                <span className="text-xs text-muted-foreground">{hint}</span>
            )}
        </div>
    );
}

/**
 * Cari özeti: bakiye; vadeli çalışan hesapta kredi limiti, kullanılabilir
 * limit ve vadesi geçen borç.
 */
export function LedgerSummaryCards({ ledger }: { ledger: LedgerData }) {
    const s = ledger.summary;
    const credit = s.terms === 'credit';

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div
                className={cn(
                    'grid grid-cols-1 gap-px bg-border sm:grid-cols-2',
                    credit && 'lg:grid-cols-4',
                )}
            >
                <Tile label={t('Bakiye')} hint={s.side_label}>
                    <BalanceText balance={s} />
                </Tile>
                {s.terms_label && (
                    <Tile
                        label={t('Çalışma şekli')}
                        hint={
                            credit && s.term_days
                                ? t(':term_days gün vade', {
                                      term_days: s.term_days,
                                  })
                                : undefined
                        }
                    >
                        {s.terms_label}
                    </Tile>
                )}
                {credit && (
                    <Tile
                        label={t('Kullanılabilir limit')}
                        tone={s.over_limit ? 'danger' : undefined}
                        hint={
                            s.credit_limit
                                ? s.pending_approval
                                    ? t(
                                          'Limit :limit · risk :risk · onayda :pending',
                                          {
                                              limit: s.credit_limit,
                                              risk: s.risk,
                                              pending: s.pending_approval,
                                          },
                                      )
                                    : t('Limit :limit · risk :risk', {
                                          limit: s.credit_limit,
                                          risk: s.risk,
                                      })
                                : t('Limitsiz')
                        }
                    >
                        {s.available ?? t('Limitsiz')}
                    </Tile>
                )}
                {credit && (
                    <Tile
                        label={t('Vadesi geçen')}
                        tone={s.overdue ? 'danger' : undefined}
                        hint={
                            s.overdue_count
                                ? t(':overdue_count sipariş', {
                                      overdue_count: s.overdue_count,
                                  })
                                : t('Yok')
                        }
                    >
                        {s.overdue ?? '—'}
                    </Tile>
                )}
            </div>
        </Card>
    );
}

/**
 * Vadesi geçmiş ve yaklaşan açık borçlar (sipariş bazında, FIFO dağıtımına göre kalan).
 */
export function LedgerDues({
    ledger,
    orderLink,
}: {
    ledger: LedgerData;
    orderLink: (order: NonNullable<LedgerRow['order']>) => ReactNode;
}) {
    const dues = ledger.dues;

    if (!dues || (dues.overdue.length === 0 && dues.upcoming.length === 0)) {
        return null;
    }

    const row = (d: LedgerDue, overdue: boolean) => (
        <li
            key={d.id}
            className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
        >
            <div className="min-w-0">
                <div className="font-medium">
                    {d.order ? orderLink(d.order) : t('Borç')}
                </div>
                <div className="text-xs text-muted-foreground">
                    {t('Vade :due_date · toplam :total', {
                        due_date: formatDate(d.due_date),
                        total: d.total,
                    })}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Badge variant={overdue ? 'destructive' : 'outline'}>
                    {overdue
                        ? t(':days gün geçti', { days: d.days })
                        : d.days === 0
                          ? t('Bugün')
                          : t(':days gün kaldı', { days: d.days })}
                </Badge>
                <span className="font-semibold tabular-nums">{d.open}</span>
            </div>
        </li>
    );

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="border-b px-4 py-3 text-sm font-semibold">
                {t('Açık borçlar')}
            </div>
            <ul className="divide-y">
                {dues.overdue.map((d) => row(d, true))}
                {dues.upcoming.map((d) => row(d, false))}
            </ul>
        </Card>
    );
}
