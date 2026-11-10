import { Link } from '@inertiajs/react';
import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/account/status-badge';
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
import {
    index as ordersIndex,
    show as orderShow,
} from '@/routes/panel/shop/orders';
import type { MemberOverview } from '@/types';

/** Siparişler sekmesi: son siparişler tablosu. */
export function MemberOrdersTab({
    orders,
    accountQuery,
}: {
    orders: MemberOverview['orders'];
    accountQuery: { query: { account: string } } | null;
}) {
    return (
        <RecordsCard
            title={t('Siparişler')}
            empty={t('Henüz sipariş yok.')}
            count={orders.data.length}
            total={orders.total}
            allHref={accountQuery && ordersIndex(accountQuery)}
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Sipariş')}</TableHead>
                        <TableHead>{t('Tarih')}</TableHead>
                        <TableHead>{t('Durum')}</TableHead>
                        <TableHead>{t('Ödeme')}</TableHead>
                        <TableHead className="text-right">
                            {t('Tutar')}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {orders.data.map((order) => (
                        <TableRow key={order.id}>
                            <TableCell>
                                <Link
                                    href={orderShow(order.id)}
                                    className="font-medium hover:underline"
                                >
                                    #{order.number}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    {t(':items_count ürün', {
                                        items_count: order.items_count,
                                    })}
                                </div>
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                {formatDateTime(order.created_at)}
                            </TableCell>
                            <TableCell>
                                <OrderStatusBadge
                                    status={order.status}
                                    label={order.status_label}
                                />
                            </TableCell>
                            <TableCell>
                                <PaymentStatusBadge
                                    status={order.payment_status}
                                    label={order.payment_status_label}
                                />
                            </TableCell>
                            <TableCell className="text-right font-medium tabular-nums">
                                {order.grand_total}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </RecordsCard>
    );
}
