import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArrowRight,
    FileText,
    Minus,
    Plus,
    Save,
    Search,
    ShoppingBasket,
    Store,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
    TotalsList,
} from '@/components/admin/page';
import { DealerBasketTable } from '@/components/dealer-shop/basket-table';
import { BulkOrderDialog } from '@/components/dealer-shop/bulk-order-dialog';
import type { BulkOrderResult } from '@/components/dealer-shop/bulk-order-dialog';
import { SavedCartDialog } from '@/components/dealer-shop/saved-cart-dialog';
import { SearchSelect } from '@/components/search-select';
import type { SearchSelectOption } from '@/components/search-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { isPositive } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/app';
import cart from '@/routes/app/shop/cart';
import checkout from '@/routes/app/shop/checkout';
import { search as productSearch } from '@/routes/app/shop/products';
import quoteList from '@/routes/app/shop/quote-list';
import savedCarts from '@/routes/app/shop/saved-carts';
import type { BasketLine, BasketTotals } from '@/types';

type ProductHit = {
    id: string;
    sku: string;
    name: string;
    brand: string | null;
    image: string;
    unit: string | null;
    price_formatted: string | null;
    stock: number | null;
    /** Bayinin bölge deposundaki stok (depo atanmışsa). */
    region_stock?: number | null;
    min_qty: number;
    max_qty: number | null;
    purchasable: boolean;
    quote_only: boolean;
    ordered_qty?: number;
};

type Props = {
    lines: BasketLine[];
    totals: BasketTotals;
    quoteCount: number;
    savedCartCount: number;
    categories: SearchSelectOption[];
    brands: SearchSelectOption[];
    frequentProducts: ProductHit[];
    shopUrl: string | null;
    bulkResult: BulkOrderResult | null;
};

/** Ürün satırı: adet + Sepete ekle (satılamıyorsa Teklif listesine ekle). */
function ProductRow({ product }: { product: ProductHit }) {
    const min = Math.max(1, product.min_qty);
    const [qty, setQty] = useState(min);
    const [busy, setBusy] = useState(false);
    const toQuote = !product.purchasable;

    const add = () =>
        router.post(
            toQuote ? quoteList.add.url() : cart.add.url(),
            { product_id: product.id, quantity: qty },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setQty(min);
                },
            },
        );

    return (
        <li className="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap">
            <img
                src={product.image}
                alt=""
                className="size-12 shrink-0 rounded-md border bg-white object-contain"
                loading="lazy"
            />
            <div className="min-w-0 flex-1">
                <div className="line-clamp-2 text-sm font-medium">
                    {product.name}
                </div>
                <div className="text-xs text-muted-foreground">
                    {product.brand ? `${product.brand} · ` : ''}
                    {product.sku}
                    {product.stock !== null
                        ? t(' · Stok :stock', { stock: product.stock })
                        : ''}
                    {product.region_stock !== null &&
                    product.region_stock !== undefined
                        ? t(' (bölge deponuzda :region_stock)', {
                              region_stock: product.region_stock,
                          })
                        : ''}
                    {product.ordered_qty
                        ? t(' · :ordered_qty adet aldınız', {
                              ordered_qty: product.ordered_qty,
                          })
                        : ''}
                </div>
                {product.quote_only ? (
                    <Badge variant="outline" className="mt-1">
                        {t('Teklifle satılır')}
                    </Badge>
                ) : !product.purchasable ? (
                    <Badge variant="destructive" className="mt-1">
                        {t('Stok yetersiz')}
                    </Badge>
                ) : null}
            </div>
            <div className="w-28 text-right text-sm font-semibold tabular-nums">
                {product.price_formatted ?? '—'}
            </div>
            <div className="flex w-28 items-center">
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    className="rounded-r-none"
                    onClick={() => setQty((q) => Math.max(min, q - 1))}
                    aria-label={t(':product_name adedini azalt', {
                        product_name: product.name,
                    })}
                >
                    <Minus />
                </Button>
                <Input
                    type="number"
                    inputMode="numeric"
                    min={min}
                    max={product.max_qty ?? undefined}
                    value={qty}
                    onChange={(e) =>
                        setQty(
                            Math.max(
                                min,
                                Number.parseInt(e.target.value, 10) || min,
                            ),
                        )
                    }
                    aria-label={t(':name adedi', { name: product.name })}
                    className="h-8 [appearance:textfield] rounded-none border-x-0 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    className="rounded-l-none"
                    onClick={() =>
                        setQty((q) =>
                            product.max_qty
                                ? Math.min(product.max_qty, q + 1)
                                : q + 1,
                        )
                    }
                    aria-label={t(':product_name adedini artır', {
                        product_name: product.name,
                    })}
                >
                    <Plus />
                </Button>
            </div>
            <Button
                type="button"
                size="sm"
                variant={toQuote ? 'outline' : 'default'}
                className="w-40"
                disabled={busy}
                onClick={add}
            >
                {busy ? (
                    <Spinner data-icon="inline-start" />
                ) : toQuote ? (
                    <FileText data-icon="inline-start" />
                ) : (
                    <ShoppingBasket data-icon="inline-start" />
                )}
                {toQuote ? t('Teklif listesine') : t('Sepete ekle')}
            </Button>
        </li>
    );
}

/**
 * Ürün arama: ad / stok kodu + kategori / marka. Filtre yokken sık
 * aldıklarınız listelenir.
 */
function ProductFinder({
    categories,
    brands,
    frequent,
}: {
    categories: SearchSelectOption[];
    brands: SearchSelectOption[];
    frequent: ProductHit[];
}) {
    const [q, setQ] = useState('');
    const [category, setCategory] = useState('');
    const [brand, setBrand] = useState('');
    const [results, setResults] = useState<ProductHit[]>([]);
    const [loading, setLoading] = useState(false);
    const active = q.trim().length >= 2 || category !== '' || brand !== '';

    useEffect(() => {
        if (!active) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(
                productSearch.url({
                    query: {
                        q: q.trim(),
                        category_id: category,
                        brand_id: brand,
                    },
                }),
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                },
            )
                .then((r) => (r.ok ? r.json() : []))
                .then((json: ProductHit[]) => setResults(json))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, category, brand, active]);

    const shown = active ? results : frequent;

    return (
        <SectionCard
            title={t('Ürün ara')}
            description={t(
                'Ürün adı ya da stok koduyla arayın, kategori veya marka seçerek listeleyin; adedi seçip sepete ekleyin.',
            )}
            flush
        >
            <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('Ürün adı veya stok kodu')}
                        className="pl-9"
                        aria-label={t('Ürün ara')}
                        autoFocus
                    />
                </div>
                <SearchSelect
                    aria-label={t('Kategori')}
                    options={categories}
                    value={category}
                    onChange={setCategory}
                    placeholder={t('Tüm kategoriler')}
                    searchPlaceholder={t('Kategori ara…')}
                    clearable
                />
                <SearchSelect
                    aria-label={t('Marka')}
                    options={brands}
                    value={brand}
                    onChange={setBrand}
                    placeholder={t('Tüm markalar')}
                    searchPlaceholder={t('Marka ara…')}
                    clearable
                />
            </div>

            {!active && frequent.length > 0 ? (
                <div className="border-b px-4 py-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Sık aldıklarınız')}
                </div>
            ) : null}

            {loading ? (
                <div className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
                    <Spinner /> {t('Aranıyor…')}
                </div>
            ) : shown.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    {active
                        ? t('Ürün bulunamadı.')
                        : t('Aramaya başlayın ya da kategori / marka seçin.')}
                </p>
            ) : (
                <ul className="max-h-[28rem] divide-y overflow-y-auto">
                    {shown.map((product) => (
                        <ProductRow key={product.id} product={product} />
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

/** Bayi paneli › Sipariş ver: ürün arama + sepet; vitrine gerek kalmadan sipariş / teklif. */
export default function AppOrder({
    lines,
    totals,
    savedCartCount,
    categories,
    brands,
    frequentProducts,
    shopUrl,
    bulkResult,
}: Props) {
    const blocked = lines.some((l) => !l.purchasable || !l.in_stock);
    const count = lines.reduce((sum, l) => sum + l.quantity, 0);

    return (
        <>
            <Head title={t('Sipariş ver')} />
            <Page>
                <PageTitle
                    title={t('Sipariş ver')}
                    trail={[
                        { title: t('Panel'), href: dashboard() },
                        { title: t('Sipariş ver') },
                    ]}
                    meta={
                        lines.length > 0 ? (
                            <>
                                <span>
                                    {t(
                                        'Sepette :lines_length çeşit, :count adet',
                                        { lines_length: lines.length, count },
                                    )}
                                </span>
                                <MetaDot />
                                <span className="tabular-nums">
                                    {t('Toplam :total_formatted', {
                                        total_formatted: totals.total_formatted,
                                    })}
                                </span>
                            </>
                        ) : undefined
                    }
                    actions={
                        <>
                            <BulkOrderDialog />
                            <Button variant="outline" asChild>
                                <Link href={savedCarts.index()}>
                                    <Archive data-icon="inline-start" />
                                    {t('Kayıtlı sepetler')}
                                    {savedCartCount > 0
                                        ? ` (${savedCartCount})`
                                        : ''}
                                </Link>
                            </Button>
                            {lines.length > 0 && (
                                <SavedCartDialog
                                    trigger={
                                        <Button variant="outline">
                                            <Save data-icon="inline-start" />
                                            {t('Sepeti kaydet')}
                                        </Button>
                                    }
                                />
                            )}
                            {shopUrl ? (
                                <Button variant="outline" asChild>
                                    <a href={shopUrl}>
                                        <Store data-icon="inline-start" />
                                        {t('Mağazaya git')}
                                    </a>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                {bulkResult && bulkResult.failed.length > 0 && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            <p className="font-medium">
                                {t(
                                    'Excel’den :added ürün eklendi, :failed satır eklenemedi:',
                                    {
                                        added: bulkResult.added,
                                        failed: bulkResult.failed.length,
                                    },
                                )}
                            </p>
                            <ul className="mt-2 max-h-60 list-disc overflow-y-auto pl-5 text-sm">
                                {bulkResult.failed.map((row, i) => (
                                    <li key={`${row.code}-${i}`}>
                                        <span className="font-mono">
                                            {row.code || '—'}
                                        </span>
                                        {row.quantity !== null &&
                                            ` × ${row.quantity}`}
                                        : {row.reason}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <ProductFinder
                    categories={categories}
                    brands={brands}
                    frequent={frequentProducts}
                />

                {lines.length === 0 ? (
                    <Empty className="border bg-card">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ShoppingBasket />
                            </EmptyMedia>
                            <EmptyTitle>{t('Sepetiniz boş')}</EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Yukarıdan ürün arayıp sepete ekleyin ya da kayıtlı bir sepetten sipariş verin.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <EntityLayout>
                        <EntityMain>
                            <DealerBasketTable
                                lines={lines}
                                updateForm={cart.update.form()}
                                removeForm={(id) => cart.remove.form(id)}
                                showPrices
                                toolbar={
                                    <h2 className="font-medium">
                                        {t('Sepetiniz')}
                                    </h2>
                                }
                            />
                        </EntityMain>
                        <EntitySidebar>
                            <SectionCard title={t('Sipariş özeti')}>
                                <div className="flex flex-col gap-4">
                                    <TotalsList
                                        rows={[
                                            ...(isPositive(totals.discount) &&
                                            totals.discount_formatted
                                                ? [
                                                      {
                                                          label: t(
                                                              'Kampanya indirimi (KDV hariç)',
                                                          ),
                                                          value: `−${totals.discount_formatted}`,
                                                      },
                                                  ]
                                                : []),
                                            {
                                                label: t(
                                                    'Ara toplam (KDV hariç)',
                                                ),
                                                value: totals.subtotal_formatted,
                                            },
                                            {
                                                label: t('KDV'),
                                                value: totals.tax_formatted,
                                            },
                                            {
                                                label: totals.carrier
                                                    ? t('Kargo (:carrier)', {
                                                          carrier:
                                                              totals.carrier,
                                                      })
                                                    : t('Kargo'),
                                                value: totals.shipping_formatted,
                                                muted: true,
                                            },
                                        ]}
                                        total={{
                                            label: t('Genel toplam'),
                                            value: totals.total_formatted,
                                        }}
                                    />
                                    {totals.nudge && (
                                        <Alert>
                                            <AlertDescription>
                                                {t(
                                                    ':amount daha ekleyin, ":name" kampanyasından yararlanın.',
                                                    {
                                                        amount: totals.nudge
                                                            .amount_formatted,
                                                        name: totals.nudge.name,
                                                    },
                                                )}{' '}
                                                ({totals.nudge.summary})
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {totals.campaign && (
                                        <Alert>
                                            <AlertDescription>
                                                <span className="font-medium text-foreground">
                                                    {t(
                                                        'Uygulanan kampanya: :name',
                                                        {
                                                            name: totals
                                                                .campaign.name,
                                                        },
                                                    )}
                                                </span>{' '}
                                                ({totals.campaign.summary})
                                                {totals.campaign.others > 0
                                                    ? t(
                                                          ' · Sepetinize :others kampanya daha uyuyor; en avantajlı olan uygulandı.',
                                                          {
                                                              others: totals
                                                                  .campaign
                                                                  .others,
                                                          },
                                                      )
                                                    : ''}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {blocked && (
                                        <Alert variant="destructive">
                                            <AlertDescription>
                                                {t(
                                                    'Sepetinizde yalnızca teklifle satılan ya da stoğu yetersiz ürün var. Bu ürünleri çıkarın ya da “Teklif al” ile tüm sepet için teklif isteyin.',
                                                )}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {/* Sipariş ver → ödeme; Teklif al → sepet teklif listesine taşınır. */}
                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                        {blocked ? (
                                            <Button size="lg" disabled>
                                                {t('Sipariş ver')}
                                                <ArrowRight data-icon="inline-end" />
                                            </Button>
                                        ) : (
                                            <Button size="lg" asChild>
                                                <Link href={checkout.index()}>
                                                    {t('Sipariş ver')}
                                                    <ArrowRight data-icon="inline-end" />
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(cart.toQuote.url())
                                            }
                                        >
                                            <FileText data-icon="inline-start" />
                                            {t('Teklif al')}
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Kargo ücreti ödeme adımında seçtiğiniz kargo firmasına ve sepetin desisine göre kesinleşir.',
                                        )}
                                    </p>
                                </div>
                            </SectionCard>
                        </EntitySidebar>
                    </EntityLayout>
                )}
            </Page>
        </>
    );
}

AppOrder.layout = {
    breadcrumbs: [{ title: t('Sipariş ver'), href: cart.index() }],
};
