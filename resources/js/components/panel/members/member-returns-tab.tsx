import { Link } from '@inertiajs/react';
import { ReturnStatusBadge } from '@/components/account/status-badge';
import { RecordsCard } from '@/components/panel/members/records-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { show as orderShow } from '@/routes/panel/shop/orders';
import {
    index as returnsIndex,
    show as returnShow,
} from '@/routes/panel/shop/returns';
import type { MemberOverview } from '@/types';

/** İadeler sekmesi: son iade talepleri tablosu. */
export function MemberReturnsTab({
    returns,
    accountQuery,
}: {
    returns: MemberOverview['returns'];
    accountQuery: { query: { account: string } } | null;
}) {
    return (
        <RecordsCard
            title={t('İadeler')}
            empty={t('Henüz iade talebi yok.')}
            count={returns.data.length}
            total={returns.total}
            allHref={accountQuery && returnsIndex(accountQuery)}
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('İade')}</TableHead>
                        <TableHead>{t('Sipariş')}</TableHead>
                        <TableHead>{t('Tarih')}</TableHead>
                        <TableHead>{t('Durum')}</TableHead>
                        <TableHead className="text-right">
                            {t('İade tutarı')}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {returns.data.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell>
                                <Link
                                    href={returnShow(item.id)}
                                    className="font-medium hover:underline"
                                >
                                    #{item.number}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    {item.reason_label} ·{' '}
                                    {t(':count ürün', {
                                        count: item.items_count,
                                    })}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Link
                                    href={orderShow(item.order_id)}
                                    className="text-sm hover:underline"
                                >
                                    #{item.order_number ?? '-'}
                                </Link>
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                {formatDateTime(item.created_at)}
                            </TableCell>
                            <TableCell>
                                <ReturnStatusBadge
                                    status={item.status}
                                    label={item.status_label}
                                />
                            </TableCell>
                            <TableCell className="text-right font-medium tabular-nums">
                                {item.refund_amount ?? '—'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </RecordsCard>
    );
}
