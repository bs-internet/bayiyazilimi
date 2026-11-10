import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Clock,
    FileText,
    PackageX,
    Receipt,
    RotateCcw,
    ShoppingCart,
    TrendingUp,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { OrderStatusBadge } from '@/components/account/status-badge';
import { KpiTile } from '@/components/dashboard/kpi-tile';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toNumber } from '@/lib/decimal';
import { formatDate, formatMoney, setBaseCurrency } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/panel';
import { index as membersIndex } from '@/routes/panel/members';
import {
    index as ordersIndex,
    show as orderShow,
} from '@/routes/panel/shop/orders';
import { index as productsIndex } from '@/routes/panel/shop/products';
import { index as quotesIndex } from '@/routes/panel/shop/quotes';
import { index as returnsIndex } from '@/routes/panel/shop/returns';
import { activity as activityIndex } from '@/routes/panel/tools';

/** Tutar KPI'ları kesin ondalık metin ("1234.50"), sayım KPI'ları tam sayıdır. */
type Kpi<T = string> = { value: T; formatted?: string; change: number | null };

type Props = {
    /** Ana para birimi. */
    currency: string;
    can: {
        orders: boolean;
        quotes: boolean;
        returns: boolean;
        products: boolean;
        members: boolean;
        activity: boolean;
    };
    periodDays: number;
    kpis: { revenue: Kpi; orders: Kpi<number>; average_order: Kpi } | null;
    newMembers: {
        total: number;
        /** Müşteri hesapları yoksa (vitrin kurulu değil) null. */
        customers: number | null;
        dealers: number;
        change: number | null;
    } | null;
    dailyRevenue: { date: string; revenue: string; orders: number }[];
    ordersByStatus: { status: string; label: string; count: number }[];
    recentOrders: {
        id: string;
        number: string;
        customer_name: string;
        email: string;
        status: string;
        status_label: string;
        grand_total: string;
        created_at: string | null;
    }[];
    topProducts: {
        sku: string;
        name: string;
        product_id: string | null;
        quantity: number;
        revenue: string;
    }[];
    pending: {
        quotes: number | null;
        returns: number | null;
        awaiting_payment: number | null;
        out_of_stock: number | null;
    };
    activity: {
        id: string;
        description: string;
        event: string | null;
        subject_label: string | null;
        causer_name: string | null;
        created_at: string | null;
    }[];
};

const revenueConfig = {
    revenue: { label: t('Ciro'), color: 'var(--chart-1)' },
} satisfies ChartConfig;

const statusColors: Record<string, string> = {
    pending: 'var(--chart-3)',
    processing: 'var(--chart-2)',
    shipped: 'var(--chart-1)',
    completed: 'var(--chart-4)',
    cancelled: 'var(--chart-5)',
    failed: 'var(--muted-foreground)',
};

const compactTl = new Intl.NumberFormat('tr-TR', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const relativeFormat = new Intl.RelativeTimeFormat('tr', { numeric: 'auto' });

function relativeTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['second', 60],
        ['minute', 60],
        ['hour', 24],
        ['day', 7],
        ['week', 4.35],
        ['month', 12],
        ['year', Number.POSITIVE_INFINITY],
    ];
    let amount = seconds;

    for (const [unit, size] of steps) {
        if (Math.abs(amount) < size) {
            return relativeFormat.format(Math.round(amount), unit);
        }

        amount /= size;
    }

    return formatDate(value);
}

function shortDay(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

function RevenueCard({
    data,
    total,
    periodDays,
}: {
    data: { date: string; revenue: number; orders: number }[];
    total: string | undefined;
    periodDays: number;
}) {
    return (
        <Card className="xl:col-span-2">
            <CardHeader>
                <CardTitle>{t('Gelir')}</CardTitle>
                <CardDescription>
                    {t('Son :period_days günün günlük cirosu (TL)', {
                        period_days: periodDays,
                    })}
                </CardDescription>
                {total && (
                    <CardAction className="text-right">
                        <div className="text-xl font-semibold tabular-nums">
                            {total}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {t('Toplam')}
                        </div>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent className="px-2 sm:px-6">
                <ChartContainer
                    config={revenueConfig}
                    className="aspect-auto h-64 w-full sm:h-72"
                >
                    <AreaChart data={data} margin={{ left: 4, right: 8 }}>
                        <defs>
                            <linearGradient
                                id="fillRevenue"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="5%"
                                    stopColor="var(--color-revenue)"
                                    stopOpacity={0.35}
                                />
                                <stop
                                    offset="95%"
                                    stopColor="var(--color-revenue)"
                                    stopOpacity={0.02}
                                />
                            </linearGradient>
                        </defs>
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="date"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            minTickGap={24}
                            tickFormatter={shortDay}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            width={48}
                            tickFormatter={(v: number) => compactTl.format(v)}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={
                                <ChartTooltipContent
                                    indicator="line"
                                    labelFormatter={(_, payload) => {
                                        const date = payload?.[0]?.payload
                                            ?.date as string | undefined;

                                        return date ? formatDate(date) : '';
                                    }}
                                    formatter={(value, _name, item) => (
                                        <div className="flex w-full items-center justify-between gap-4">
                                            <span className="text-muted-foreground">
                                                {t('Ciro · :orders sipariş', {
                                                    orders: (
                                                        item.payload as {
                                                            orders: number;
                                                        }
                                                    ).orders,
                                                })}
                                            </span>
                                            <span className="font-mono font-medium tabular-nums">
                                                {formatMoney(Number(value))}
                                            </span>
                                        </div>
                                    )}
                                />
                            }
                        />
                        <Area
                            dataKey="revenue"
                            type="monotone"
                            fill="url(#fillRevenue)"
                            stroke="var(--color-revenue)"
                            strokeWidth={2}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function StatusDonutCard({
    data,
    periodDays,
}: {
    data: Props['ordersByStatus'];
    periodDays: number;
}) {
    const total = data.reduce((sum, row) => sum + row.count, 0);
    const config = Object.fromEntries(
        data.map((row) => [
            row.status,
            { label: row.label, color: statusColors[row.status] },
        ]),
    ) satisfies ChartConfig;
    const visible = data.filter((row) => row.count > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Sipariş durumları')}</CardTitle>
                <CardDescription>
                    {t('Son :period_days gün', { period_days: periodDays })}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {total === 0 ? (
                    <EmptyState>{t('Bu dönemde sipariş yok.')}</EmptyState>
                ) : (
                    <div className="relative mx-auto aspect-square w-full max-w-56">
                        <ChartContainer
                            config={config}
                            className="aspect-square w-full"
                        >
                            <PieChart>
                                <ChartTooltip
                                    cursor={false}
                                    content={
                                        <ChartTooltipContent
                                            nameKey="status"
                                            hideLabel
                                        />
                                    }
                                />
                                <Pie
                                    data={visible}
                                    dataKey="count"
                                    nameKey="status"
                                    innerRadius="62%"
                                    outerRadius="90%"
                                    paddingAngle={2}
                                    strokeWidth={0}
                                >
                                    {visible.map((row) => (
                                        <Cell
                                            key={row.status}
                                            fill={`var(--color-${row.status})`}
                                        />
                                    ))}
                                </Pie>
                            </PieChart>
                        </ChartContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-3xl font-semibold tabular-nums">
                                {total}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('sipariş')}
                            </span>
                        </div>
                    </div>
                )}
                <ul className="grid gap-2 text-sm">
                    {data.map((row) => (
                        <li
                            key={row.status}
                            className="flex items-center gap-2"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{
                                    backgroundColor: statusColors[row.status],
                                }}
                            />
                            <span className="flex-1 text-muted-foreground">
                                {row.label}
                            </span>
                            <span className="font-medium tabular-nums">
                                {row.count}
                            </span>
                            <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">
                                {total > 0
                                    ? `${Math.round((row.count / total) * 100)}%`
                                    : '—'}
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function PendingCard({ pending }: { pending: Props['pending'] }) {
    const items: {
        key: string;
        label: string;
        hint: string;
        count: number | null;
        href: string;
        icon: LucideIcon;
        tone: string;
    }[] = [
        {
            key: 'quotes',
            label: t('İşlem bekleyen teklifler'),
            hint: t('Yanıt bekleyen teklif talepleri'),
            count: pending.quotes,
            href: quotesIndex({ query: { status: 'awaiting' } }).url,
            icon: FileText,
            tone: 'bg-primary/10 text-primary',
        },
        {
            key: 'returns',
            label: t('Bekleyen iadeler'),
            hint: t('İncelenecek iade talepleri'),
            count: pending.returns,
            href: returnsIndex({ query: { status: 'pending' } }).url,
            icon: RotateCcw,
            tone: 'bg-chart-2/15 text-chart-2',
        },
        {
            key: 'awaiting_payment',
            label: t('Ödeme bekleyen'),
            hint: t('Havale/EFT onayı bekleyen siparişler'),
            count: pending.awaiting_payment,
            href: ordersIndex({
                query: {
                    payment_status: 'pending',
                    payment_method: 'bank_transfer',
                },
            }).url,
            icon: Clock,
            tone: 'bg-chart-3/25 text-foreground',
        },
        {
            key: 'out_of_stock',
            label: t('Stokta olmayan'),
            hint: t('Stoğu 0 veya altında ürünler'),
            count: pending.out_of_stock,
            href: productsIndex({ query: { stock: 'out' } }).url,
            icon: PackageX,
            tone: 'bg-chart-5/15 text-chart-5',
        },
    ];
    const visible = items.filter((item) => item.count !== null);

    if (visible.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Bekleyen işler')}</CardTitle>
                <CardDescription>
                    {t('İlgilenilmesi gereken kayıtlar')}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-3">
                <ul className="grid gap-1">
                    {visible.map((item) => (
                        <li key={item.key}>
                            <Link
                                href={item.href}
                                className="group flex items-center gap-3 rounded-lg p-3 transition-colors hover:bg-muted"
                            >
                                <span
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                        item.tone,
                                    )}
                                >
                                    <item.icon className="size-5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">
                                        {item.label}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {item.hint}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'text-xl font-semibold tabular-nums',
                                        item.count === 0 &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {item.count}
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function RecentOrdersCard({ orders }: { orders: Props['recentOrders'] }) {
    return (
        <Card className="gap-4 xl:col-span-2">
            <CardHeader>
                <CardTitle>{t('Son siparişler')}</CardTitle>
                <CardDescription>
                    {t('En son oluşturulan siparişler')}
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
                    <EmptyState>{t('Henüz sipariş yok.')}</EmptyState>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="pl-6">
                                    {t('Sipariş')}
                                </TableHead>
                                <TableHead>{t('Müşteri')}</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    {t('Tarih')}
                                </TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                                <TableHead className="pr-6 text-right">
                                    {t('Tutar')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.map((order) => (
                                <TableRow key={order.id}>
                                    <TableCell className="pl-6">
                                        <Link
                                            href={orderShow(order.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {order.number}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <div className="max-w-40 truncate">
                                            {order.customer_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground md:hidden">
                                            {formatDate(order.created_at)}
                                        </div>
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        {formatDate(order.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <OrderStatusBadge
                                            status={order.status}
                                            label={order.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="pr-6 text-right font-medium tabular-nums">
                                        {order.grand_total}
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

function TopProductsCard({
    products,
    periodDays,
}: {
    products: Props['topProducts'];
    periodDays: number;
}) {
    const max = Math.max(1, ...products.map((p) => p.quantity));

    return (
        <Card className="gap-4 xl:col-span-2">
            <CardHeader>
                <CardTitle>{t('En çok satanlar')}</CardTitle>
                <CardDescription>
                    {t('Son :period_days gün, satılan adede göre', {
                        period_days: periodDays,
                    })}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {products.length === 0 ? (
                    <EmptyState>{t('Bu dönemde satış yok.')}</EmptyState>
                ) : (
                    <ol className="grid gap-4">
                        {products.map((product, index) => (
                            <li
                                key={product.sku}
                                className="flex items-center gap-4"
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold text-muted-foreground">
                                    {index + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="truncate text-sm font-medium">
                                            {product.name}
                                        </span>
                                        <span className="shrink-0 text-sm font-medium tabular-nums">
                                            {product.revenue}
                                        </span>
                                    </div>
                                    <div className="mt-1.5 flex items-center gap-3">
                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${(product.quantity / max) * 100}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                            {product.sku} ·{' '}
                                            {t(':count adet', {
                                                count: product.quantity.toLocaleString(
                                                    'tr-TR',
                                                ),
                                            })}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

function ActivityCard({ items }: { items: Props['activity'] }) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{t('Son hareketler')}</CardTitle>
                <CardDescription>
                    {t('Sistem aktivite kayıtları')}
                </CardDescription>
                <CardAction>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={activityIndex()}>{t('Tümü')}</Link>
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <EmptyState>{t('Henüz kayıt yok.')}</EmptyState>
                ) : (
                    <ol className="relative grid gap-5 before:absolute before:top-2 before:bottom-2 before:left-[5px] before:w-px before:bg-border">
                        {items.map((item) => (
                            <li key={item.id} className="relative pl-6">
                                <span className="absolute top-1.5 left-0 size-[11px] rounded-full border-2 border-card bg-primary ring-1 ring-primary/30" />
                                <p className="text-sm leading-snug">
                                    {item.causer_name && (
                                        <span className="font-medium">
                                            {item.causer_name}{' '}
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">
                                        {item.description}
                                    </span>
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {relativeTime(item.created_at)}
                                    {item.subject_label
                                        ? ` · ${item.subject_label}`
                                        : ''}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    currency,
    can,
    periodDays,
    kpis,
    newMembers,
    dailyRevenue,
    ordersByStatus,
    recentOrders,
    topProducts,
    pending,
    activity,
}: Props) {
    setBaseCurrency(currency);
    const hasPending = Object.values(pending).some((v) => v !== null);

    return (
        <>
            <Head title={t('Panel')} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Panel')}
                    description={t('Son :period_days günün özeti.', {
                        period_days: periodDays,
                    })}
                    actions={
                        can.orders && (
                            <Button asChild>
                                <Link href={ordersIndex()}>
                                    <ShoppingCart data-icon="inline-start" />
                                    {t('Siparişler')}
                                </Link>
                            </Button>
                        )
                    }
                />

                {(kpis || newMembers) && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {kpis && (
                            <>
                                <KpiTile
                                    icon={Banknote}
                                    label={t('Ciro')}
                                    value={kpis.revenue.formatted}
                                    delta={kpis.revenue.change}
                                    hint={t('Son :period_days gün', {
                                        period_days: periodDays,
                                    })}
                                />
                                <KpiTile
                                    icon={Receipt}
                                    tone="sky"
                                    label={t('Sipariş')}
                                    value={kpis.orders.value.toLocaleString(
                                        'tr-TR',
                                    )}
                                    delta={kpis.orders.change}
                                    hint={t('Son :period_days gün', {
                                        period_days: periodDays,
                                    })}
                                    href={ordersIndex().url}
                                />
                                <KpiTile
                                    icon={TrendingUp}
                                    tone="amber"
                                    label={t('Ortalama sipariş')}
                                    value={kpis.average_order.formatted}
                                    delta={kpis.average_order.change}
                                    hint={t('Sipariş başına tutar')}
                                />
                            </>
                        )}
                        {newMembers && (
                            <KpiTile
                                icon={UserPlus}
                                tone="green"
                                label={t('Yeni üye')}
                                value={newMembers.total.toLocaleString('tr-TR')}
                                delta={newMembers.change}
                                hint={
                                    newMembers.customers === null
                                        ? t(':dealers bayi', {
                                              dealers: newMembers.dealers,
                                          })
                                        : t(
                                              ':customers müşteri · :dealers bayi',
                                              {
                                                  customers:
                                                      newMembers.customers,
                                                  dealers: newMembers.dealers,
                                              },
                                          )
                                }
                                href={membersIndex().url}
                            />
                        )}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    {can.orders && (
                        <RevenueCard
                            // Grafik ekseni sayı ister (yalnız çizim; tutarlar metin kalır).
                            data={dailyRevenue.map((d) => ({
                                ...d,
                                revenue: toNumber(d.revenue),
                            }))}
                            total={kpis?.revenue.formatted}
                            periodDays={periodDays}
                        />
                    )}
                    {hasPending && <PendingCard pending={pending} />}

                    {can.orders && (
                        <>
                            <RecentOrdersCard orders={recentOrders} />
                            <StatusDonutCard
                                data={ordersByStatus}
                                periodDays={periodDays}
                            />
                            <TopProductsCard
                                products={topProducts}
                                periodDays={periodDays}
                            />
                        </>
                    )}

                    {can.activity && <ActivityCard items={activity} />}
                </div>

                {!can.orders && !hasPending && !can.activity && (
                    <Card>
                        <CardContent>
                            <EmptyState>
                                {t(
                                    'Panelde görüntüleyebileceğiniz bir özet yok. Menüden yetkili olduğunuz bölümlere ulaşabilirsiniz.',
                                )}
                            </EmptyState>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: t('Panel'),
            href: dashboard(),
        },
    ],
};
