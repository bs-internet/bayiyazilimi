import { Head, Link, router } from '@inertiajs/react';
import { History, Layers, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
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
import { edit as productEdit } from '@/routes/panel/shop/products';
import { index, lots as lotsRoute, movements } from '@/routes/panel/shop/stock';
import type { Paginated, ShopOption } from '@/types';

type LotRow = {
    id: string;
    code: string;
    kind: string | null;
    product_id: string;
    sku: string | null;
    name: string | null;
    unit: string | null;
    expires_at: string | null;
    expired: boolean;
    expiring: boolean;
    quantity: number;
    places: {
        warehouse: string | null;
        location: string | null;
        quantity: number;
    }[];
};

type Props = {
    lots: Paginated<LotRow>;
    filters: { q?: string; state?: string; warehouse?: string };
    warehouses: ShopOption[];
    warningDays: number;
};

export default function StockLots({
    lots,
    filters,
    warehouses,
    warningDays,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const states: ShopOption[] = [
        { value: 'in_stock', label: t('Stokta olanlar') },
        {
            value: 'expiring',
            label: t('SKT yaklaşan (:warning_days gün)', {
                warning_days: warningDays,
            }),
        },
        { value: 'expired', label: t('SKT geçmiş (stokta)') },
        { value: 'all', label: t('Tümü (geçmiş dahil)') },
    ];

    const apply = (patch: Props['filters']) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...patch }).filter(([, v]) => v),
        );
        router.get(lotsRoute.url(), next, {
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
            <Head title={t('Lot / Seri No')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu'), href: index() },
                        { title: t('Lot / Seri No') },
                    ]}
                    title={t('Lot / Seri No')}
                    meta={t(':total kayıt', { total: lots.total })}
                    description={t(
                        "Lot ve seri numaralarının stok ve SKT durumu. 'Hareketler' ile bir lotun nereden geldiğini ve hangi siparişlerle çıktığını izleyin. Süresi geçmiş lotlar satılabilir stoğa sayılmaz.",
                    )}
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
                                            'Lot / seri no, ürün adı ya da SKU ara',
                                        )}
                                        className="pl-10"
                                        aria-label={t('Lot ara')}
                                    />
                                </div>
                                <Button type="submit" variant="secondary">
                                    <Search data-icon="inline-start" />
                                    {t('Ara')}
                                </Button>
                                {Object.values(filters).some((v) => v) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setQ('');
                                            router.get(lotsRoute.url());
                                        }}
                                    >
                                        <RotateCcw data-icon="inline-start" />
                                        {t('Temizle')}
                                    </Button>
                                )}
                            </form>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <SearchSelect
                                    aria-label={t('Durum')}
                                    value={filters.state ?? 'in_stock'}
                                    onChange={(v) => apply({ state: v })}
                                    options={states}
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
                        lots.data.length > 0 ? (
                            <DataPagination
                                paginator={lots}
                                label={t('kayıt')}
                            />
                        ) : undefined
                    }
                >
                    {lots.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Layers />
                                </EmptyMedia>
                                <EmptyTitle>{t('Kayıt yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Lot / seri takipli ürünlerin girişleri burada listelenir.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Lot / Seri no')}</TableHead>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('SKT')}</TableHead>
                                    <TableHead>{t('Yer')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Stok')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lots.data.map((lot) => (
                                    <TableRow key={lot.id}>
                                        <TableCell>
                                            <div className="font-mono font-medium">
                                                {lot.code}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {lot.kind}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            <Link
                                                href={productEdit(
                                                    lot.product_id,
                                                )}
                                                className="line-clamp-1 font-medium whitespace-normal hover:underline"
                                            >
                                                {lot.name ?? '—'}
                                            </Link>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {lot.sku}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {lot.expires_at ? (
                                                <div className="flex flex-col items-start gap-1">
                                                    <span className="tabular-nums">
                                                        {formatDate(
                                                            lot.expires_at,
                                                        )}
                                                    </span>
                                                    {lot.expired && (
                                                        <Badge variant="destructive">
                                                            {t('Süresi geçmiş')}
                                                        </Badge>
                                                    )}
                                                    {lot.expiring && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                                                        >
                                                            {t(
                                                                'SKT yaklaşıyor',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {lot.places.length === 0
                                                ? '—'
                                                : lot.places.map((p, i) => (
                                                      <div key={i}>
                                                          {p.warehouse}{' '}
                                                          <span className="font-mono text-muted-foreground">
                                                              {p.location}
                                                          </span>{' '}
                                                          · {p.quantity}
                                                      </div>
                                                  ))}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {lot.quantity} {lot.unit}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':code hareketleri',
                                                    { code: lot.code },
                                                )}
                                                title={t(
                                                    'Hareketler (izlenebilirlik)',
                                                )}
                                                asChild
                                            >
                                                <Link
                                                    href={movements({
                                                        query: { lot: lot.id },
                                                    })}
                                                >
                                                    <History />
                                                </Link>
                                            </Button>
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

StockLots.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Stok Durumu'), href: index() },
        { title: t('Lot / Seri No'), href: lotsRoute() },
    ],
};
