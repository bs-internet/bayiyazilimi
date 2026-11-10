import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    FileDown,
    FileUp,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { StatusSwitch } from '@/components/shop/form-fields';
import { Badge } from '@/components/ui/badge';
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
import { Slot } from '@/lib/extensions';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { products as exportProducts } from '@/routes/panel/shop/export';
import { index as importIndex } from '@/routes/panel/shop/import';
import { create, edit, index, status } from '@/routes/panel/shop/products';
import type {
    CategoryOption,
    Paginated,
    ProductListItem,
    ShopOption,
} from '@/types';

type Filters = {
    q?: string;
    category?: string;
    brand?: string;
    status?: string;
    sale_mode?: string;
    stock?: string;
    /** Modüllerin süzgeçleri (ör. vitrin: audience). */
    [key: string]: string | undefined;
};

type Props = {
    products: Paginated<ProductListItem>;
    filters: Filters;
    categories: CategoryOption[];
    brands: ShopOption[];
    saleModes: ShopOption[];
    /** Modüllerin liste verisi, modül anahtarıyla (`shop.products.index_props`). */
    extensions: Record<string, unknown>;
};

const STATUS_OPTIONS: ShopOption[] = [
    { value: 'active', label: t('Aktif') },
    { value: 'passive', label: t('Pasif') },
];

const STOCK_OPTIONS: ShopOption[] = [
    { value: 'in', label: t('Stokta var') },
    { value: 'out', label: t('Stokta yok') },
];

const SALE_MODE_SHORT: Record<string, string> = {
    cart: 'Sepet',
    quote: 'Teklif',
    both: t('Sepet + Teklif'),
};

/** Boş filtreleri atar; sorgu dizesine yalnız dolu olanlar gider. */
function clean(filters: Filters): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filters).filter(
            ([, v]) => v !== undefined && v !== null && v !== '',
        ),
    ) as Record<string, string>;
}

function SelectFilter({
    label,
    value,
    options,
    placeholder,
    onChange,
}: {
    label: string;
    value?: string;
    options: ShopOption[];
    placeholder: string;
    onChange: (value: string) => void;
}) {
    return (
        <SearchSelect
            aria-label={label}
            value={value ?? ''}
            onChange={onChange}
            options={options}
            placeholder={placeholder}
            clearable
        />
    );
}

function StockBadge({ product }: { product: ProductListItem }) {
    if (!product.track_stock) {
        return <Badge variant="outline">{t('Takipsiz')}</Badge>;
    }

    if (product.stock <= 0) {
        return (
            <Badge className="bg-destructive/10 text-destructive">
                {t('Tükendi')}
            </Badge>
        );
    }

    return (
        <Badge className="bg-emerald-500/10 text-emerald-700 tabular-nums dark:text-emerald-400">
            {t('Stokta :stock', { stock: product.stock })}
        </Badge>
    );
}

export default function ProductIndex({
    products,
    filters,
    categories,
    brands,
    saleModes,
    extensions,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const active = clean(filters);
    const hasFilter = Object.keys(active).length > 0;

    const apply = (patch: Filters) => {
        router.get(index.url(), clean({ ...filters, ...patch }), {
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
            <Head title={t('Ürünler')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Katalog') }, { title: t('Ürünler') }]}
                    title={t('Ürünler')}
                    meta={
                        <span className="tabular-nums">
                            {t(':total ürün', { total: products.total })}
                        </span>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={exportProducts.url({
                                        query: active,
                                    })}
                                >
                                    <FileDown data-icon="inline-start" />
                                    {t('Dışa aktar')}
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={importIndex()}>
                                    <FileUp data-icon="inline-start" />
                                    {t('İçe aktar')}
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus data-icon="inline-start" />
                                    {t('Yeni ürün')}
                                </Link>
                            </Button>
                        </>
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
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder={t(
                                            'Ad, SKU, barkod veya model no ara',
                                        )}
                                        className="pl-9"
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
                            <div className="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
                                <SelectFilter
                                    label={t('Kategori')}
                                    value={filters.category}
                                    options={categories}
                                    placeholder={t('Tüm kategoriler')}
                                    onChange={(v) => apply({ category: v })}
                                />
                                <SelectFilter
                                    label={t('Marka')}
                                    value={filters.brand}
                                    options={brands}
                                    placeholder={t('Tüm markalar')}
                                    onChange={(v) => apply({ brand: v })}
                                />
                                <SelectFilter
                                    label={t('Durum')}
                                    value={filters.status}
                                    options={STATUS_OPTIONS}
                                    placeholder={t('Durum: tümü')}
                                    onChange={(v) => apply({ status: v })}
                                />
                                <SelectFilter
                                    label={t('Satış biçimi')}
                                    value={filters.sale_mode}
                                    options={saleModes}
                                    placeholder={t('Satış biçimi: tümü')}
                                    onChange={(v) => apply({ sale_mode: v })}
                                />
                                <Slot
                                    name="products.filters"
                                    filters={filters}
                                    apply={apply}
                                    extensions={extensions}
                                    SelectFilter={SelectFilter}
                                />
                                <SelectFilter
                                    label={t('Stok')}
                                    value={filters.stock}
                                    options={STOCK_OPTIONS}
                                    placeholder={t('Stok: tümü')}
                                    onChange={(v) => apply({ stock: v })}
                                />
                            </div>
                        </div>
                    }
                    footer={
                        products.data.length > 0 ? (
                            <DataPagination
                                paginator={products}
                                label={t('ürün')}
                            />
                        ) : undefined
                    }
                >
                    {products.data.length === 0 ? (
                        <Empty className="py-16">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Package />
                                </EmptyMedia>
                                <EmptyTitle>{t('Ürün bulunamadı')}</EmptyTitle>
                                <EmptyDescription>
                                    {hasFilter
                                        ? t('Filtreye uygun ürün yok.')
                                        : t('Henüz ürün eklenmemiş.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Kategori')}</TableHead>
                                    <TableHead>{t('Stok')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Fiyat')}
                                    </TableHead>
                                    <TableHead>{t('Satış')}</TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-10">
                                        <span className="sr-only">
                                            {t('İşlem')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.data.map((product) => (
                                    <ProductRow
                                        key={product.id}
                                        product={product}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

function ProductRow({ product }: { product: ProductListItem }) {
    // "Sitede gör": yalnız aktif ve herkese açık adresi olan üründe (vitrin modülü).
    const storefrontUrl =
        product.status === 'active' ? product.storefront_url : null;

    return (
        <TableRow className={cn(product.status !== 'active' && 'opacity-70')}>
            <TableCell className="min-w-72">
                <div className="flex items-center gap-3">
                    {product.image ? (
                        <img
                            src={product.image}
                            alt=""
                            className="size-11 shrink-0 rounded-md border bg-white object-contain"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                            <Package className="size-4" />
                        </div>
                    )}
                    <div className="min-w-0">
                        <Link
                            href={edit(product.id)}
                            className="line-clamp-2 font-medium hover:text-primary hover:underline"
                        >
                            {product.name}
                        </Link>
                        <div className="truncate text-xs text-muted-foreground">
                            {t('SKU: :sku', { sku: product.sku })}
                            {product.model_number
                                ? ` · ${t('Model: :model', { model: product.model_number })}`
                                : ''}
                        </div>
                    </div>
                </div>
            </TableCell>
            <TableCell className="max-w-48">
                <div className="truncate">{product.category ?? '—'}</div>
                {product.brand && (
                    <div className="truncate text-xs text-muted-foreground">
                        {product.brand}
                    </div>
                )}
            </TableCell>
            <TableCell>
                <StockBadge product={product} />
            </TableCell>
            <TableCell className="text-right whitespace-nowrap">
                <div className="font-medium tabular-nums">
                    {product.price_formatted}
                </div>
                <div className="text-xs text-muted-foreground">
                    {t('+ KDV %:tax_rate', {
                        tax_rate: Number(product.tax_rate),
                    })}
                </div>
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap items-center gap-1">
                    <Badge
                        variant={
                            product.sale_mode === 'quote'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {SALE_MODE_SHORT[product.sale_mode] ??
                            product.sale_mode_label}
                    </Badge>
                    {product.badges.map((b) => (
                        <Badge
                            key={b.label}
                            className="bg-primary/10 text-primary"
                        >
                            {b.label}
                        </Badge>
                    ))}
                </div>
            </TableCell>
            <TableCell>
                <StatusSwitch
                    status={product.status}
                    url={status(product.id).url}
                    label={t('Ürün durumunu değiştir')}
                />
            </TableCell>
            <TableCell className="text-right">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('İşlemler')}
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuItem asChild>
                            <Link href={edit(product.id)}>
                                <Pencil />
                                {t('Düzenle')}
                            </Link>
                        </DropdownMenuItem>
                        {storefrontUrl && (
                            <DropdownMenuItem asChild>
                                <a
                                    href={storefrontUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink />
                                    {t('Sitede gör')}
                                </a>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </TableCell>
        </TableRow>
    );
}

ProductIndex.layout = {
    breadcrumbs: [{ title: t('Ürünler'), href: index() }],
};
