import { Link } from '@inertiajs/react';
import { ShoppingBag, Store } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { OrderStatusBadge } from '@/components/account/status-badge';
import { TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import { home } from '@/routes';
import appCart from '@/routes/app/shop/cart';
import type { Paginated } from '@/types';

export type OrderRow = {
    id: string;
    number: string;
    status: string;
    status_label: string;
    payment_status_label: string;
    payment_method_label: string;
    items_count: number;
    grand_total: string;
    created_at: string | null;
};

export type OrdersIndexProps = {
    orders: Paginated<OrderRow>;
};

export function OrdersIndexContent({
    area,
    orders,
}: OrdersIndexProps & { area: AccountArea }) {
    const routes = accountRoutes(area);

    return (
        <>
            <AccountTitle
                area={area}
                title={t('Siparişlerim')}
                description={t('Verdiğiniz siparişler ve durumları.')}
            />

            <TableCard
                footer={
                    orders.last_page > 1 ? (
                        <DataPagination
                            paginator={orders}
                            label={t('sipariş')}
                        />
                    ) : undefined
                }
            >
                {orders.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ShoppingBag />
                            </EmptyMedia>
                            <EmptyTitle>
                                {t('Henüz siparişiniz yok')}
                            </EmptyTitle>
                            <EmptyDescription>
                                {t('Verdiğiniz siparişler burada listelenir.')}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild variant="secondary">
                                <a
                                    href={
                                        area === 'app'
                                            ? appCart.index().url
                                            : home().url
                                    }
                                >
                                    <Store data-icon="inline-start" />
                                    {t('Alışverişe başla')}
                                </a>
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Sipariş No')}</TableHead>
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
                                    <TableCell className="font-medium">
                                        <Link
                                            href={routes.orders.show(order.id)}
                                            className="hover:underline"
                                        >
                                            {order.number}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {t(':items_count ürün', {
                                                items_count: order.items_count,
                                            })}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(order.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <OrderStatusBadge
                                            status={order.status}
                                            label={order.status_label}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-sm">
                                            {order.payment_status_label}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {order.payment_method_label}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {order.grand_total}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </TableCard>
        </>
    );
}
