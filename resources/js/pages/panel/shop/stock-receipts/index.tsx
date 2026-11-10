import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Plus, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { Button } from '@/components/ui/button';
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
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index as stockIndex } from '@/routes/panel/shop/stock';
import { create, index, show } from '@/routes/panel/shop/stock-receipts';
import type { Paginated, ShopOption } from '@/types';

type ReceiptRow = {
    id: string;
    number: string;
    supplier: string | null;
    document_type: string;
    document_no: string;
    document_date: string;
    warehouse: string | null;
    lines: number;
    quantity: number;
    user: string | null;
    created_at: string;
};

type Props = {
    receipts: Paginated<ReceiptRow>;
    filters: { q?: string; supplier?: string; warehouse?: string };
    suppliers: ShopOption[];
    warehouses: ShopOption[];
};

export default function StockReceiptIndex({
    receipts,
    filters,
    suppliers,
    warehouses,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const hasFilter = Object.values(filters).some((v) => v);

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

    return (
        <>
            <Head title={t('Mal Kabul')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu'), href: stockIndex() },
                        { title: t('Mal Kabul') },
                    ]}
                    title={t('Mal Kabul')}
                    meta={t(':total kayıt', { total: receipts.total })}
                    description={t(
                        'Tedarikçi irsaliyesi / faturasıyla yapılan stok girişleri. Kayıtlar değiştirilmez; düzeltme stok çıkışıyla yapılır.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" />
                                {t('Yeni mal kabul')}
                            </Link>
                        </Button>
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
                                        type="search"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder={t(
                                            'Kayıt no ya da belge no ara',
                                        )}
                                        className="pl-10"
                                        aria-label={t('Mal kabul ara')}
                                    />
                                </div>
                                <Button type="submit" variant="secondary">
                                    <Search data-icon="inline-start" />
                                    {t('Ara')}
                                </Button>
                                {hasFilter && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setQ('');
                                            router.get(index.url());
                                        }}
                                    >
                                        <RotateCcw data-icon="inline-start" />
                                        {t('Temizle')}
                                    </Button>
                                )}
                            </form>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <SearchSelect
                                    aria-label={t('Tedarikçi')}
                                    value={filters.supplier ?? ''}
                                    onChange={(v) => apply({ supplier: v })}
                                    options={suppliers}
                                    placeholder={t('Tüm tedarikçiler')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Depo')}
                                    value={filters.warehouse ?? ''}
                                    onChange={(v) => apply({ warehouse: v })}
                                    options={warehouses}
                                    placeholder={t('Tüm depolar')}
                                    clearable
                                />
                            </div>
                        </div>
                    }
                    footer={
                        receipts.data.length > 0 ? (
                            <DataPagination
                                paginator={receipts}
                                label={t('kayıt')}
                            />
                        ) : undefined
                    }
                >
                    {receipts.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ClipboardList />
                                </EmptyMedia>
                                <EmptyTitle>{t('Mal kabul yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Tedarikçiden gelen ürünleri "Yeni mal kabul" ile stoğa girin.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Kayıt')}</TableHead>
                                    <TableHead>{t('Tedarikçi')}</TableHead>
                                    <TableHead>{t('Belge')}</TableHead>
                                    <TableHead>{t('Depo')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Satır / Adet')}
                                    </TableHead>
                                    <TableHead>{t('Kullanıcı')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.data.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>
                                            <Link
                                                href={show(r.id)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                {r.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(r.created_at)}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {r.supplier ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                {r.document_type}{' '}
                                                <span className="font-mono">
                                                    {r.document_no}
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(r.document_date)}
                                            </div>
                                        </TableCell>
                                        <TableCell>{r.warehouse}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {r.lines} / {r.quantity}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {r.user ?? '—'}
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

StockReceiptIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Mal Kabul'), href: index() },
    ],
};
