import { Head, Link, router } from '@inertiajs/react';
import {
    Boxes,
    History,
    ImageOff,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { StockOperationForm } from '@/components/shop/stock-operation-form';
import type {
    StockLocationOption,
    StockLocationQuantity,
} from '@/components/shop/stock-operation-form';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/panel';
import { edit } from '@/routes/panel/shop/products';
import { index, movements } from '@/routes/panel/shop/stock';
import type { CategoryOption, Paginated, ShopOption } from '@/types';

type StockRow = {
    id: string;
    sku: string;
    name: string;
    image: string | null;
    category: string | null;
    stock: number;
    track_stock: boolean;
    /** Satılabilir stok kritik eşiğe (ürüne özel ya da genel) inmiş. */
    low_stock: boolean;
    threshold: number;
    tracking: 'none' | 'lot' | 'serial';
    expiry_tracking: boolean;
    quantities: Record<string, number>;
    total: number;
    locations: (StockLocationQuantity & { expiring?: boolean })[];
};

type WarehouseColumn = {
    id: string;
    code: string;
    name: string;
    is_sellable: boolean;
    is_active: boolean;
};

type Props = {
    products: Paginated<StockRow>;
    warehouses: WarehouseColumn[];
    categories: CategoryOption[];
    locations: StockLocationOption[];
    filters: {
        q?: string;
        stock?: string;
        warehouse?: string;
        category?: string;
    };
    /** Lot / seri no / SKT takibi açık mı. */
    traceability: boolean;
};

const STOCK_FILTERS: ShopOption[] = [
    { value: 'in', label: t('Stokta var') },
    { value: 'out', label: t('Stokta yok') },
    { value: 'low', label: t('Kritik stokta') },
    { value: 'negative', label: t('Eksi bakiyeli') },
    { value: 'expiring', label: t("SKT'si yaklaşan") },
    { value: 'expired', label: t("SKT'si geçmiş (stokta)") },
];

function Thumb({ src, alt }: { src: string | null; alt: string }) {
    return (
        <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
            {src ? (
                <img
                    src={src}
                    alt={alt}
                    className="size-full object-cover"
                    loading="lazy"
                />
            ) : (
                <ImageOff className="size-4 text-muted-foreground" />
            )}
        </div>
    );
}

export default function StockIndex({
    products,
    warehouses,
    categories,
    locations,
    filters,
    traceability,
}: Props) {
    const [selected, setSelected] = useState<StockRow | null>(null);
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const hasFilter = Object.values(filters).some((v) => v);
    const [q, setQ] = useState(filters.q ?? '');

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

    const openOperation = (row: StockRow) => {
        setSelected(row);
        setFormKey((k) => k + 1);
        setOpen(true);
    };

    return (
        <>
            <Head title={t('Stok Durumu')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu') },
                    ]}
                    title={t('Stok Durumu')}
                    meta={t(':total ürün · :warehouses_length depo', {
                        total: products.total,
                        warehouses_length: warehouses.length,
                    })}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={movements()}>
                                <History data-icon="inline-start" />
                                {t('Stok hareketleri')}
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
                                            'Ürün adı, SKU veya barkod ara',
                                        )}
                                        className="pl-10"
                                        aria-label={t('Ürün ara')}
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
                                    aria-label={t('Kategori')}
                                    value={filters.category ?? ''}
                                    onChange={(v) => apply({ category: v })}
                                    options={categories}
                                    placeholder={t('Tüm kategoriler')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Depo')}
                                    value={filters.warehouse ?? ''}
                                    onChange={(v) => apply({ warehouse: v })}
                                    options={warehouses.map((w) => ({
                                        value: w.id,
                                        label: w.name,
                                    }))}
                                    placeholder={t('Tüm depolar')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Stok durumu')}
                                    value={filters.stock ?? ''}
                                    onChange={(v) => apply({ stock: v })}
                                    options={
                                        traceability
                                            ? STOCK_FILTERS
                                            : STOCK_FILTERS.filter(
                                                  (o) =>
                                                      !o.value.startsWith(
                                                          'expir',
                                                      ),
                                              )
                                    }
                                    placeholder={t('Stok durumu: tümü')}
                                    clearable
                                />
                            </div>
                        </div>
                    }
                    footer={
                        products.data.length > 0 ? (
                            <div className="flex flex-col gap-3">
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        '* Satışa kapalı depo (satılabilir stoğa dahil edilmez).',
                                    )}
                                </p>
                                <DataPagination
                                    paginator={products}
                                    label={t('ürün')}
                                />
                            </div>
                        ) : undefined
                    }
                >
                    {products.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Boxes />
                                </EmptyMedia>
                                <EmptyTitle>{t('Kayıt bulunamadı')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('Filtreye uygun ürün yok.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    {warehouses.map((w) => (
                                        <TableHead
                                            key={w.id}
                                            className="text-right whitespace-nowrap"
                                            title={w.name}
                                        >
                                            {w.code}
                                            {!w.is_sellable && '*'}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-right">
                                        {t('Toplam')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Satılabilir')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="min-w-64">
                                            <div className="flex items-center gap-3">
                                                <Thumb
                                                    src={row.image}
                                                    alt={row.name}
                                                />
                                                <div className="min-w-0">
                                                    <Link
                                                        href={edit(row.id)}
                                                        className="line-clamp-1 font-medium whitespace-normal hover:underline"
                                                    >
                                                        {row.name}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('SKU:')}{' '}
                                                        <span className="font-mono">
                                                            {row.sku}
                                                        </span>
                                                        {row.category &&
                                                            ` · ${row.category}`}
                                                        {row.tracking ===
                                                            'lot' &&
                                                            t(' · Lot takipli')}
                                                        {row.tracking ===
                                                            'serial' &&
                                                            t(
                                                                ' · Seri no takipli',
                                                            )}
                                                    </div>
                                                    {row.locations.some(
                                                        (l) => l.expired,
                                                    ) && (
                                                        <div className="text-xs font-medium text-destructive">
                                                            {t(
                                                                "SKT'si geçmiş lot var",
                                                            )}
                                                        </div>
                                                    )}
                                                    {!row.locations.some(
                                                        (l) => l.expired,
                                                    ) &&
                                                        row.locations.some(
                                                            (l) => l.expiring,
                                                        ) && (
                                                            <div className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                                                {t(
                                                                    "SKT'si yaklaşan lot var",
                                                                )}
                                                            </div>
                                                        )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        {warehouses.map((w) => {
                                            const qty =
                                                row.quantities[w.id] ?? 0;

                                            return (
                                                <TableCell
                                                    key={w.id}
                                                    title={row.locations
                                                        .filter(
                                                            (l) =>
                                                                l.warehouse_id ===
                                                                w.id,
                                                        )
                                                        .map(
                                                            (l) =>
                                                                `${l.code}${l.lot ? ` · ${l.lot}` : ''}: ${l.quantity}`,
                                                        )
                                                        .join(', ')}
                                                    className={cn(
                                                        'text-right tabular-nums',
                                                        qty < 0 &&
                                                            'font-medium text-destructive',
                                                        qty === 0 &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    {qty}
                                                </TableCell>
                                            );
                                        })}
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {row.total}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.track_stock ? (
                                                <span
                                                    className={cn(
                                                        row.low_stock &&
                                                            'font-medium text-amber-700 dark:text-amber-400',
                                                    )}
                                                    title={
                                                        row.low_stock
                                                            ? t(
                                                                  'Kritik stok (eşik: :threshold)',
                                                                  {
                                                                      threshold:
                                                                          row.threshold,
                                                                  },
                                                              )
                                                            : undefined
                                                    }
                                                >
                                                    {row.stock}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t('Takipsiz')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={t(
                                                            'İşlemler',
                                                        )}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            openOperation(row)
                                                        }
                                                    >
                                                        <Boxes />
                                                        {t('Stok işlemi')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={movements({
                                                                query: {
                                                                    product:
                                                                        row.id,
                                                                },
                                                            })}
                                                        >
                                                            <History />
                                                            {t('Hareketler')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={edit(row.id)}
                                                        >
                                                            <Pencil />
                                                            {t('Ürünü düzenle')}
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

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{t('Stok işlemi')}</SheetTitle>
                        <SheetDescription>
                            {selected
                                ? `${selected.sku} — ${selected.name}`
                                : ''}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        {selected && (
                            <StockOperationForm
                                key={formKey}
                                row={selected}
                                warehouses={warehouses}
                                locations={locations}
                                onDone={() => setOpen(false)}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Stok Durumu'), href: index() },
    ],
};
