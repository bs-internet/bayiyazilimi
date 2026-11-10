import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    Plus,
    FileText,
    MoreHorizontal,
    RotateCcw,
    Search,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { QuoteStatusBadge } from '@/components/account/status-badge';
import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
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
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as orderShow } from '@/routes/panel/shop/orders';
import { create, edit as show, index } from '@/routes/panel/shop/quotes';
import type { Paginated, QuoteListItem, ShopOption } from '@/types';

type Props = {
    quotes: Paginated<QuoteListItem>;
    filters: { q?: string; status?: string; source?: string };
    statuses: ShopOption[];
    sources: ShopOption[];
    awaiting: number;
};

export default function QuoteIndex({
    quotes,
    filters,
    statuses,
    sources,
    awaiting,
}: Props) {
    const hasFilter = Object.values(filters).some((v) => v);
    const [term, setTerm] = useState(filters.q ?? '');

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
        apply({ q: term });
    };

    const reset = () => {
        setTerm('');
        router.get(index.url(), {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Teklifler')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Satış') }, { title: t('Teklifler') }]}
                    title={t('Teklifler')}
                    meta={
                        t(':total teklif', { total: quotes.total }) +
                        (awaiting > 0
                            ? t(' · :awaiting işlem bekliyor', { awaiting })
                            : '')
                    }
                    description={t(
                        'Müşteri talepleri ve sizin hazırladığınız teklifler. Fiyatlandırıp gönderdiğiniz teklifi müşteri onaylar, revize ister ya da reddeder.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" />
                                {t('Yeni teklif')}
                            </Link>
                        </Button>
                    }
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
                                    name="q"
                                    type="search"
                                    value={term}
                                    onChange={(e) => setTerm(e.target.value)}
                                    placeholder={t(
                                        'Teklif no, müşteri, firma, e-posta',
                                    )}
                                    className="pl-10"
                                    aria-label={t('Ara')}
                                />
                            </div>
                            <div className="sm:w-48">
                                <SearchSelect
                                    aria-label={t('Kaynak')}
                                    value={filters.source ?? ''}
                                    onChange={(v) => apply({ source: v })}
                                    options={sources}
                                    placeholder={t('Kaynak: tümü')}
                                    clearable
                                />
                            </div>
                            <div className="sm:w-60">
                                <SearchSelect
                                    aria-label={t('Teklif durumu')}
                                    value={filters.status ?? ''}
                                    onChange={(v) => apply({ status: v })}
                                    options={statuses}
                                    placeholder={t('Teklif durumu: tümü')}
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
                        quotes.data.length > 0 ? (
                            <DataPagination
                                paginator={quotes}
                                label={t('teklif')}
                            />
                        ) : undefined
                    }
                >
                    {quotes.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {t('Teklif bulunamadı')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {t('Filtreye uygun teklif yok.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Teklif')}</TableHead>
                                    <TableHead>{t('Müşteri')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead>{t('Geçerlilik')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Kalem')}
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
                                {quotes.data.map((quote) => (
                                    <TableRow key={quote.id}>
                                        <TableCell className="whitespace-nowrap">
                                            <Link
                                                href={show(quote.id)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                #{quote.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground tabular-nums">
                                                {formatDateTime(
                                                    quote.created_at,
                                                )}
                                                {quote.source === 'admin'
                                                    ? t(' · Yönetici teklifi')
                                                    : t(' · Müşteri talebi')}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-64">
                                            <div className="truncate font-medium">
                                                {quote.customer_name}
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {quote.company
                                                    ? `${quote.company} · ${quote.email}`
                                                    : quote.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <QuoteStatusBadge
                                                status={quote.status}
                                                label={quote.status_label}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                            {quote.valid_until
                                                ? formatDate(quote.valid_until)
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {quote.items_count ?? 0}
                                        </TableCell>
                                        <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                                            {quote.grand_total_formatted}
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
                                                                quote.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            {t('Detayı aç')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {quote.order_id && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={orderShow(
                                                                    quote.order_id,
                                                                )}
                                                            >
                                                                <ShoppingCart />
                                                                {t(
                                                                    'Siparişi aç',
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

QuoteIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Teklifler'), href: index() },
    ],
};
