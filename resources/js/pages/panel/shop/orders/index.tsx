import { Head, Link, router } from '@inertiajs/react';
import {
    ClipboardList,
    Eye,
    FileText,
    MoreHorizontal,
    RotateCcw,
    Search,
    ShoppingCart,
    UserSearch,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/account/status-badge';
import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-permissions';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index, pickList, show } from '@/routes/panel/shop/orders';
import { edit as quoteShow } from '@/routes/panel/shop/quotes';
import type { OrderListItem, Paginated, ShopOption } from '@/types';

type Props = {
    orders: Paginated<OrderListItem>;
    filters: {
        q?: string;
        status?: string;
        payment_status?: string;
        payment_method?: string;
        from?: string;
        to?: string;
        account?: string;
        invoice?: string;
    };
    statuses: ShopOption[];
    paymentStatuses: ShopOption[];
    paymentMethods: ShopOption[];
};

const INVOICE_OPTIONS: ShopOption[] = [
    { value: 'kesildi', label: t('Faturalandı') },
    { value: 'kesilmedi', label: t('Tamamlandı, faturalanmadı') },
];

/** Fatura sütunu: yalnız tamamlanan siparişlerde anlamlı. */
function InvoiceCell({ order }: { order: OrderListItem }) {
    if (order.invoice_number) {
        return (
            <Badge className="bg-emerald-500/10 whitespace-nowrap text-emerald-700 dark:text-emerald-400">
                {t('Faturalandı · :invoice_number', {
                    invoice_number: order.invoice_number,
                })}
            </Badge>
        );
    }

    if (order.status === 'completed') {
        return (
            <Badge
                variant="outline"
                className="whitespace-nowrap text-amber-700 dark:text-amber-400"
            >
                {t('Faturalanmadı')}
            </Badge>
        );
    }

    return <span className="text-muted-foreground">—</span>;
}

export default function OrderIndex({
    orders,
    filters,
    statuses,
    paymentStatuses,
    paymentMethods,
}: Props) {
    const hasFilter = Object.values(filters).some((v) => v);
    const [q, setQ] = useState(filters.q ?? '');
    const canPick = useCan('panel.shop.orders.pick-list');
    const [picked, setPicked] = useState<string[]>([]);
    const pageIds = orders.data.map((o) => o.id);
    const allPicked =
        pageIds.length > 0 && pageIds.every((id) => picked.includes(id));

    const togglePick = (id: string, on: boolean) =>
        setPicked((prev) =>
            on ? [...new Set([...prev, id])] : prev.filter((p) => p !== id),
        );

    const togglePage = (on: boolean) =>
        setPicked((prev) =>
            on
                ? [...new Set([...prev, ...pageIds])]
                : prev.filter((p) => !pageIds.includes(p)),
        );

    // Boş filtreler sorgu dizesine gitmez.
    const apply = (patch: Props['filters']) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...patch }).filter(([, v]) => v),
        );
        router.get(index.url(), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({ q });
    };

    const reset = () => {
        setQ('');
        router.get(index.url(), {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Siparişler')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Satış') }, { title: t('Siparişler') }]}
                    title={t('Siparişler')}
                    meta={t(':total sipariş', { total: orders.total })}
                    actions={
                        canPick ? (
                            <Button
                                variant={
                                    picked.length > 0 ? 'default' : 'outline'
                                }
                                asChild
                            >
                                <Link
                                    href={pickList(
                                        picked.length > 0
                                            ? { query: { orders: picked } }
                                            : undefined,
                                    )}
                                >
                                    <ClipboardList data-icon="inline-start" />
                                    {picked.length > 0
                                        ? t(
                                              'Toplama listesi (:picked_length)',
                                              { picked_length: picked.length },
                                          )
                                        : t('Toplama listesi')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <TableCard
                    toolbar={
                        <div className="flex w-full flex-col gap-3">
                            <form
                                onSubmit={submitSearch}
                                className="flex w-full gap-2"
                            >
                                <div className="relative min-w-0 flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="q"
                                        type="search"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder={t(
                                            'Sipariş no, müşteri, e-posta, telefon',
                                        )}
                                        className="pl-10"
                                        aria-label={t('Sipariş ara')}
                                    />
                                </div>
                                <Button type="submit" variant="secondary">
                                    {t('Ara')}
                                </Button>
                                {hasFilter && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={reset}
                                    >
                                        <RotateCcw data-icon="inline-start" />
                                        {t('Temizle')}
                                    </Button>
                                )}
                            </form>
                            <div className="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
                                <SearchSelect
                                    aria-label={t('Sipariş durumu')}
                                    value={filters.status ?? ''}
                                    onChange={(v) => apply({ status: v })}
                                    options={statuses}
                                    placeholder={t('Sipariş durumu: tümü')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Ödeme durumu')}
                                    value={filters.payment_status ?? ''}
                                    onChange={(v) =>
                                        apply({ payment_status: v })
                                    }
                                    options={paymentStatuses}
                                    placeholder={t('Ödeme durumu: tümü')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Ödeme yöntemi')}
                                    value={filters.payment_method ?? ''}
                                    onChange={(v) =>
                                        apply({ payment_method: v })
                                    }
                                    options={paymentMethods}
                                    placeholder={t('Ödeme yöntemi: tümü')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Fatura')}
                                    value={filters.invoice ?? ''}
                                    onChange={(v) => apply({ invoice: v })}
                                    options={INVOICE_OPTIONS}
                                    placeholder={t('Fatura: tümü')}
                                    clearable
                                />
                                <Input
                                    type="date"
                                    aria-label={t('Başlangıç tarihi')}
                                    title={t('Başlangıç tarihi')}
                                    value={filters.from ?? ''}
                                    onChange={(e) =>
                                        apply({ from: e.target.value })
                                    }
                                />
                                <Input
                                    type="date"
                                    aria-label={t('Bitiş tarihi')}
                                    title={t('Bitiş tarihi')}
                                    value={filters.to ?? ''}
                                    onChange={(e) =>
                                        apply({ to: e.target.value })
                                    }
                                />
                                {filters.account && (
                                    <div className="flex h-9 min-w-0 items-center gap-2 rounded-md border bg-muted/40 pr-1 pl-3 text-sm">
                                        <span className="min-w-0 flex-1 truncate">
                                            {t('Tek hesabın siparişleri')}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label={t(
                                                'Hesap filtresini kaldır',
                                            )}
                                            onClick={() =>
                                                apply({ account: '' })
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    }
                    footer={
                        orders.data.length > 0 ? (
                            <DataPagination
                                paginator={orders}
                                label={t('sipariş')}
                            />
                        ) : undefined
                    }
                >
                    {orders.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ShoppingCart />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {t('Sipariş bulunamadı')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {t('Filtreye uygun sipariş yok.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {canPick && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={allPicked}
                                                onCheckedChange={(v) =>
                                                    togglePage(v === true)
                                                }
                                                aria-label={t(
                                                    'Sayfadaki siparişleri seç',
                                                )}
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead>{t('Sipariş')}</TableHead>
                                    <TableHead>{t('Müşteri')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead>{t('Ödeme')}</TableHead>
                                    <TableHead>{t('Fatura')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Ürün')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Toplam')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.data.map((order) => (
                                    <TableRow key={order.id}>
                                        {canPick && (
                                            <TableCell>
                                                <Checkbox
                                                    checked={picked.includes(
                                                        order.id,
                                                    )}
                                                    onCheckedChange={(v) =>
                                                        togglePick(
                                                            order.id,
                                                            v === true,
                                                        )
                                                    }
                                                    aria-label={t(
                                                        '#:number seç',
                                                        {
                                                            number: order.number,
                                                        },
                                                    )}
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell className="whitespace-nowrap">
                                            <Link
                                                href={show(order.id)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                #{order.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground tabular-nums">
                                                {formatDateTime(
                                                    order.created_at,
                                                )}
                                                {order.quote_id &&
                                                    t(' · Tekliften')}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-64">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate font-medium">
                                                    {order.customer_name}
                                                </span>
                                                {order.account_type ===
                                                    'dealer' && (
                                                    <Badge className="bg-primary/10 text-primary">
                                                        {t('Bayi')}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {order.company
                                                    ? `${order.company} · ${order.email}`
                                                    : order.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <OrderStatusBadge
                                                status={order.status}
                                                label={order.status_label}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col items-start gap-1">
                                                <PaymentStatusBadge
                                                    status={
                                                        order.payment_status
                                                    }
                                                    label={
                                                        order.payment_status_label
                                                    }
                                                />
                                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                    {order.payment_method_label}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <InvoiceCell order={order} />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {order.items_count ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                                            {order.grand_total_formatted}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={t(
                                                            'İşlemler',
                                                        )}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={show(
                                                                order.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            {t('Detayı aç')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={index({
                                                                query: order.account_id
                                                                    ? {
                                                                          account:
                                                                              order.account_id,
                                                                      }
                                                                    : {
                                                                          q: order.email,
                                                                      },
                                                            })}
                                                        >
                                                            <UserSearch />
                                                            {t(
                                                                'Müşterinin siparişleri',
                                                            )}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {order.quote_id && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={quoteShow(
                                                                    order.quote_id,
                                                                )}
                                                            >
                                                                <FileText />
                                                                {t(
                                                                    'Teklifi aç',
                                                                )}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

OrderIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Siparişler'), href: index() },
    ],
};
