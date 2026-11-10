import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgePercent,
    FileText,
    Package,
    RotateCcw,
    ShoppingCart,
    Store,
    Truck,
} from 'lucide-react';

import {
    OrderStatusBadge,
    QuoteStatusBadge,
} from '@/components/account/status-badge';
import { KpiTile } from '@/components/dashboard/kpi-tile';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppAuth } from '@/hooks/use-app-auth';
import { isPositive } from '@/lib/decimal';
import { formatDate, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/app';
import {
    index as ordersIndex,
    reorder,
    show as orderShow,
} from '@/routes/app/shop/orders';
import { index as quotesIndex } from '@/routes/app/shop/quotes';

type Stats = {
    open_orders: number;
    total_orders: number;
    pending_quotes: number;
    approved_quotes: number;
    cart_count: number;
    cart_total: string | null;
    last_order: {
        id: string;
        number: string;
        status: string;
        status_label: string;
    } | null;
};

type Props = {
    stats: Stats;
    orderUrl: string;
    recentOrders: {
        id: string;
        number: string;
        status: string;
        status_label: string;
        items_count: number;
        grand_total: string;
        created_at: string | null;
    }[];
    recentQuotes: {
        id: string;
        number: string;
        status: string;
        status_label: string;
        grand_total: string | null;
        valid_until: string | null;
        order_url: string | null;
    }[];
    dealer: { group: string | null; rate: string } | null;
    shopUrl: string | null;
};

function RecentOrdersCard({ orders }: { orders: Props['recentOrders'] }) {
    return (
        <Card className="gap-4 xl:col-span-2">
            <CardHeader>
                <CardTitle>{t('Son siparişler')}</CardTitle>
                <CardDescription>
                    {t('Tek tıkla aynı ürünleri yeniden sepete ekleyin.')}
                </CardDescription>
                <CardAction>
                    <Button asChild variant="outline" size="sm">
                        <Link href={ordersIndex()}>
                            {t('Tümü')}
                            <ArrowRight data-icon="inline-end" />
                        </Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent className="px-0">
                {orders.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                        {t('Henüz siparişiniz yok.')}
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="pl-6">
                                    {t('Sipariş')}
                                </TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                                <TableHead className="text-right">
                                    {t('Tutar')}
                                </TableHead>
                                <TableHead className="pr-6" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.map((o) => (
                                <TableRow key={o.id}>
                                    <TableCell className="pl-6">
                                        <Link
                                            href={orderShow(o.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {o.number}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDate(o.created_at)} ·{' '}
                                            {t(':count ürün', {
                                                count: o.items_count,
                                            })}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <OrderStatusBadge
                                            status={o.status}
                                            label={o.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {o.grand_total}
                                    </TableCell>
                                    <TableCell className="pr-6 text-right">
                                        <Form
                                            {...reorder.form(o.id)}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    <RotateCcw data-icon="inline-start" />
                                                    {t('Tekrar sipariş ver')}
                                                </Button>
                                            )}
                                        </Form>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function RecentQuotesCard({ quotes }: { quotes: Props['recentQuotes'] }) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{t('Teklifler')}</CardTitle>
                <CardDescription>
                    {t('Son teklif talepleriniz')}
                </CardDescription>
                <CardAction>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={quotesIndex()}>{t('Tümü')}</Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                {quotes.length === 0 ? (
                    <p className="py-6 text-sm text-muted-foreground">
                        {t(
                            'Henüz teklif talebiniz yok. Ürün sayfalarındaki “Teklif Al” ile toplu alımlarınız için fiyat isteyebilirsiniz.',
                        )}
                    </p>
                ) : (
                    <ul className="divide-y text-sm">
                        {quotes.map((q) => (
                            <li
                                key={q.id}
                                className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-medium">
                                        {q.number}
                                    </span>
                                    <QuoteStatusBadge
                                        status={q.status}
                                        label={q.status_label}
                                    />
                                </div>
                                <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground tabular-nums">
                                        {q.grand_total ??
                                            t('Fiyatlandırılıyor')}
                                    </span>
                                    {q.valid_until && (
                                        <span>
                                            {t('Geçerlilik :date', {
                                                date: formatDate(q.valid_until),
                                            })}
                                        </span>
                                    )}
                                </div>
                                {q.order_url && (
                                    <Button asChild size="sm">
                                        <a href={q.order_url}>
                                            {t('Onayla ve sipariş ver')}
                                        </a>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    stats,
    orderUrl,
    recentOrders,
    recentQuotes,
    dealer,
    shopUrl,
}: Props) {
    const { user } = useAppAuth();

    return (
        <>
            <Head title={t('Başlangıç')} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={
                        user
                            ? t('Hoş geldiniz, :user_name', {
                                  user_name: user.name,
                              })
                            : t('Başlangıç')
                    }
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{t('Siparişleriniz ve teklifleriniz.')}</span>
                            {dealer && isPositive(dealer.rate) && (
                                <Badge
                                    variant="secondary"
                                    className="bg-primary/10 text-primary"
                                >
                                    <BadgePercent />
                                    {t('Bayi indiriminiz: %:rate', {
                                        rate: formatNumber(dealer.rate),
                                    })}
                                    {dealer.group ? ` · ${dealer.group}` : ''}
                                </Badge>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            {shopUrl ? (
                                <Button asChild variant="outline">
                                    <a href={shopUrl}>
                                        <Store data-icon="inline-start" />
                                        {t('Mağazaya git')}
                                    </a>
                                </Button>
                            ) : null}
                            <Button asChild>
                                <Link href={orderUrl}>
                                    <ShoppingCart data-icon="inline-start" />
                                    {t('Sipariş ver')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiTile
                        icon={Truck}
                        label={t('Açık siparişler')}
                        value={stats.open_orders}
                        hint={t('Hazırlanan ve yoldaki siparişler')}
                        href={ordersIndex().url}
                    />
                    <KpiTile
                        icon={Package}
                        tone="sky"
                        label={t('Toplam sipariş')}
                        value={stats.total_orders}
                        hint={
                            stats.last_order
                                ? t('Son: :number · :status_label', {
                                      number: stats.last_order.number,
                                      status_label:
                                          stats.last_order.status_label,
                                  })
                                : t('Henüz sipariş yok')
                        }
                        href={
                            stats.last_order
                                ? orderShow(stats.last_order.id).url
                                : ordersIndex().url
                        }
                    />
                    <KpiTile
                        icon={FileText}
                        tone="amber"
                        label={t('Yanıtınızı bekleyen teklifler')}
                        value={stats.pending_quotes}
                        hint={
                            stats.approved_quotes > 0
                                ? t(
                                      ':approved_quotes onaylı teklif ödeme bekliyor',
                                      {
                                          approved_quotes:
                                              stats.approved_quotes,
                                      },
                                  )
                                : t('Onaylayın, revize isteyin ya da reddedin')
                        }
                        href={quotesIndex().url}
                    />
                    <KpiTile
                        icon={ShoppingCart}
                        tone="green"
                        label={t('Sepet tutarı')}
                        value={stats.cart_total ?? '—'}
                        hint={
                            stats.cart_count > 0
                                ? t(':cart_count ürün', {
                                      cart_count: stats.cart_count,
                                  })
                                : t('Sepetiniz boş')
                        }
                        href={orderUrl}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <RecentOrdersCard orders={recentOrders} />
                    <RecentQuotesCard quotes={recentQuotes} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: t('Başlangıç'),
            href: dashboard(),
        },
    ],
};
