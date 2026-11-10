import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, History, RotateCcw, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { MovementReference } from '@/components/shop/movement-reference';
import { StockMovementBadge } from '@/components/shop/stock-movement-badge';
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
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/panel';
import { edit as productEdit } from '@/routes/panel/shop/products';
import { index, movements } from '@/routes/panel/shop/stock';
import type { Paginated, ShopOption, StockMovementItem } from '@/types';

type Props = {
    movements: Paginated<StockMovementItem>;
    filters: {
        q?: string;
        warehouse?: string;
        type?: string;
        product?: string;
        lot?: string;
    };
    warehouses: ShopOption[];
    types: ShopOption[];
    product: { id: string; sku: string; name: string } | null;
    lot: { id: string; code: string } | null;
};

export default function StockMovements({
    movements: list,
    filters,
    warehouses,
    types,
    product,
    lot,
}: Props) {
    const hasFilter = Object.values(filters).some((v) => v);
    const [q, setQ] = useState(filters.q ?? '');

    // Boş filtreler sorgu dizesine gitmez.
    const apply = (patch: Props['filters']) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...patch }).filter(([, v]) => v),
        );
        router.get(movements.url(), next, {
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
        router.get(movements.url(), {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Stok Hareketleri')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu'), href: index() },
                        { title: t('Stok Hareketleri') },
                    ]}
                    title={t('Stok Hareketleri')}
                    meta={
                        product
                            ? t(':sku — :product_name · :total hareket', {
                                  sku: product.sku,
                                  product_name: product.name,
                                  total: list.total,
                              })
                            : t(':total hareket', { total: list.total })
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                <ArrowLeft data-icon="inline-start" />
                                {t('Stok durumu')}
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
                                        name="q"
                                        type="search"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder={t(
                                            'Ürün adı, SKU ya da lot / seri no ara',
                                        )}
                                        className="pl-10"
                                        aria-label={t('Hareket ara')}
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
                            <div className="grid gap-2 sm:grid-cols-3">
                                <SearchSelect
                                    aria-label={t('Hareket türü')}
                                    value={filters.type ?? ''}
                                    onChange={(v) => apply({ type: v })}
                                    options={types}
                                    placeholder={t('Hareket türü: tümü')}
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
                                {lot && (
                                    <div className="flex h-9 min-w-0 items-center gap-2 rounded-md border bg-muted/40 pr-1 pl-3 text-sm">
                                        <span className="min-w-0 flex-1 truncate font-mono">
                                            {t('Lot / Seri: :code', {
                                                code: lot.code,
                                            })}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label={t(
                                                'Lot filtresini kaldır',
                                            )}
                                            onClick={() => apply({ lot: '' })}
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                )}
                                {product && (
                                    <div className="flex h-9 min-w-0 items-center gap-2 rounded-md border bg-muted/40 pr-1 pl-3 text-sm">
                                        <Link
                                            href={productEdit(product.id)}
                                            className="min-w-0 flex-1 truncate hover:underline"
                                            title={product.name}
                                        >
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {product.sku}
                                            </span>{' '}
                                            {product.name}
                                        </Link>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label={t(
                                                'Ürün filtresini kaldır',
                                            )}
                                            onClick={() =>
                                                apply({ product: '' })
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
                        list.data.length > 0 ? (
                            <DataPagination
                                paginator={list}
                                label={t('hareket')}
                            />
                        ) : undefined
                    }
                >
                    {list.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <History />
                                </EmptyMedia>
                                <EmptyTitle>{t('Hareket yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Filtreye uygun stok hareketi bulunamadı.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Tarih')}</TableHead>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Depo')}</TableHead>
                                    <TableHead>{t('Tür')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Miktar')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Bakiye')}
                                    </TableHead>
                                    <TableHead>{t('Referans / Not')}</TableHead>
                                    <TableHead>{t('Kullanıcı')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDateTime(m.created_at)}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            <div className="line-clamp-1 font-medium whitespace-normal">
                                                {m.product_name ?? '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {t('SKU:')}{' '}
                                                <span className="font-mono">
                                                    {m.product_sku}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {m.warehouse}
                                            {m.location ? (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {m.location}
                                                </div>
                                            ) : null}
                                            {m.lot ? (
                                                <Link
                                                    href={movements({
                                                        query: {
                                                            lot: m.lot_id,
                                                        },
                                                    })}
                                                    className="block font-mono text-xs hover:underline"
                                                    title={t(
                                                        'Bu lotun hareketleri',
                                                    )}
                                                >
                                                    {t('Lot :lot', {
                                                        lot: m.lot,
                                                    })}
                                                    {m.expires_at
                                                        ? t(
                                                              ' · SKT :expires_at',
                                                              {
                                                                  expires_at:
                                                                      formatDate(
                                                                          m.expires_at,
                                                                      ),
                                                              },
                                                          )
                                                        : ''}
                                                </Link>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            <StockMovementBadge
                                                type={m.type}
                                                label={m.type_label}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right font-semibold tabular-nums',
                                                m.quantity < 0
                                                    ? 'text-destructive'
                                                    : 'text-primary',
                                            )}
                                        >
                                            {m.quantity > 0
                                                ? `+${m.quantity}`
                                                : m.quantity}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {m.balance_after}
                                        </TableCell>
                                        <TableCell className="max-w-xs text-xs whitespace-normal">
                                            <MovementReference movement={m} />
                                            {m.note ? (
                                                <div className="text-muted-foreground">
                                                    {m.note}
                                                </div>
                                            ) : null}
                                            {!m.reference && !m.note
                                                ? '—'
                                                : null}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {m.user ?? t('Sistem')}
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

StockMovements.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Stok Durumu'), href: index() },
        { title: t('Stok Hareketleri'), href: movements() },
    ],
};
