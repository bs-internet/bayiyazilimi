import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    MoreHorizontal,
    RotateCcw,
    Search,
    ShoppingCart,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { ReturnStatusBadge } from '@/components/shop/return-status-badge';
import { Button } from '@/components/ui/button';
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
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as orderShow } from '@/routes/panel/shop/orders';
import { index, show } from '@/routes/panel/shop/returns';
import type { Paginated, ShopOption } from '@/types';

type ReturnRow = {
    id: string;
    number: string;
    order_id: string;
    order_number: string | null;
    customer_name: string | null;
    email: string | null;
    status: string;
    status_label: string;
    reason_label: string;
    items_count: number;
    created_at: string | null;
};

type Props = {
    returns: Paginated<ReturnRow>;
    filters: { search?: string; status?: string };
    statuses: ShopOption[];
};

export default function ReturnIndex({ returns, filters, statuses }: Props) {
    const hasFilter = Object.values(filters).some((v) => v);
    const [term, setTerm] = useState(filters.search ?? '');

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
        apply({ search: term });
    };

    const reset = () => {
        setTerm('');
        router.get(index.url(), {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('İadeler')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Satış') }, { title: t('İadeler') }]}
                    title={t('İadeler')}
                    meta={t(':total iade talebi', { total: returns.total })}
                />

                <TableCard
                    toolbar={
                        <form
                            onSubmit={submitSearch}
                            className="flex w-full flex-col gap-2 sm:flex-row"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    name="search"
                                    type="search"
                                    value={term}
                                    onChange={(e) => setTerm(e.target.value)}
                                    placeholder={t(
                                        'İade no, sipariş no, müşteri, e-posta',
                                    )}
                                    className="pl-10"
                                    aria-label={t('Ara')}
                                />
                            </div>
                            <div className="sm:w-60">
                                <SearchSelect
                                    aria-label={t('İade durumu')}
                                    value={filters.status ?? ''}
                                    onChange={(v) => apply({ status: v })}
                                    options={statuses}
                                    placeholder={t('İade durumu: tümü')}
                                    clearable
                                />
                            </div>
                            <div className="flex gap-2">
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
                            </div>
                        </form>
                    }
                    footer={
                        returns.data.length > 0 ? (
                            <DataPagination
                                paginator={returns}
                                label={t('iade')}
                            />
                        ) : undefined
                    }
                >
                    {returns.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Undo2 />
                                </EmptyMedia>
                                <EmptyTitle>{t('İade talebi yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Filtreye uygun iade talebi bulunamadı.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('İade')}</TableHead>
                                    <TableHead>{t('Sipariş')}</TableHead>
                                    <TableHead>{t('Müşteri')}</TableHead>
                                    <TableHead>{t('Neden')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Kalem')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {returns.data.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell className="whitespace-nowrap">
                                            <Link
                                                href={show(r.id)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                #{r.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground tabular-nums">
                                                {formatDateTime(r.created_at)}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {r.order_number ? (
                                                <Link
                                                    href={orderShow(r.order_id)}
                                                    className="font-mono text-sm hover:underline"
                                                >
                                                    #{r.order_number}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-64">
                                            <div className="truncate font-medium">
                                                {r.customer_name ?? '—'}
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {r.email}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {r.reason_label}
                                        </TableCell>
                                        <TableCell>
                                            <ReturnStatusBadge
                                                status={r.status}
                                                label={r.status_label}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {r.items_count}
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
                                                        <Link href={show(r.id)}>
                                                            <Eye />
                                                            {t('Detayı aç')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={orderShow(
                                                                r.order_id,
                                                            )}
                                                        >
                                                            <ShoppingCart />
                                                            {t('Siparişi aç')}
                                                        </Link>
                                                    </DropdownMenuItem>
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

ReturnIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('İadeler'), href: index() },
    ],
};
