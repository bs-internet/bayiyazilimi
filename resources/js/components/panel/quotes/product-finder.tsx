import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { SectionCard } from '@/components/admin/page';
import { SearchSelect } from '@/components/search-select';
import type { SearchSelectOption } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { DecimalInput } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { products as productsRoute } from '@/routes/panel/shop/quotes';

import type { PickerProduct } from './lines';

/** Ürün seçici: arama + kategori / marka süzgeci; sonuçtan "Ekle". */
export function ProductFinder({
    categories,
    brands,
    currency,
    money,
    selected,
    onAdd,
}: {
    categories: SearchSelectOption[];
    brands: SearchSelectOption[];
    currency: string;
    money: (value: DecimalInput) => string;
    selected: Set<string>;
    onAdd: (product: PickerProduct) => void;
}) {
    const [q, setQ] = useState('');
    const [category, setCategory] = useState('');
    const [brand, setBrand] = useState('');
    const [results, setResults] = useState<PickerProduct[]>([]);
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
                productsRoute.url({
                    query: {
                        q: q.trim(),
                        category_id: category,
                        brand_id: brand,
                        currency,
                    },
                }),
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                },
            )
                .then((r) => (r.ok ? r.json() : { data: [] }))
                .then((json: { data: PickerProduct[] }) =>
                    setResults(json.data),
                )
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, category, brand, currency, active]);

    const shown = active ? results : [];

    return (
        <SectionCard
            title={t('Ürün ekle')}
            description={t(
                'Ada / koda göre arayın ya da kategori veya marka seçerek listeleyin.',
            )}
        >
            <div className="flex flex-col gap-3">
                <div className="grid gap-3 md:grid-cols-3">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder={t('Ürün adı veya kodu')}
                            className="pl-8"
                        />
                    </div>
                    <SearchSelect
                        options={categories}
                        value={category}
                        onChange={setCategory}
                        placeholder={t('Tüm kategoriler')}
                        searchPlaceholder={t('Kategori ara…')}
                        clearable
                    />
                    <SearchSelect
                        options={brands}
                        value={brand}
                        onChange={setBrand}
                        placeholder={t('Tüm markalar')}
                        searchPlaceholder={t('Marka ara…')}
                        clearable
                    />
                </div>

                {loading ? (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner /> {t('Aranıyor…')}
                    </div>
                ) : active && shown.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('Ürün bulunamadı.')}
                    </p>
                ) : null}

                {shown.length > 0 ? (
                    <ul className="max-h-80 divide-y overflow-y-auto rounded-md border">
                        {shown.map((product) => {
                            const added = selected.has(product.id);

                            return (
                                <li
                                    key={product.id}
                                    className="flex items-center gap-3 px-3 py-2"
                                >
                                    <img
                                        src={product.image ?? undefined}
                                        alt=""
                                        className="size-9 shrink-0 rounded border object-contain"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium">
                                            {product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {product.sku} ·{' '}
                                            {t(
                                                'Stok :stock · Liste :price + KDV',
                                                {
                                                    stock: product.stock,
                                                    price: money(product.price),
                                                },
                                            )}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={added ? 'outline' : 'default'}
                                        disabled={added}
                                        onClick={() => onAdd(product)}
                                    >
                                        {added ? (
                                            'Eklendi'
                                        ) : (
                                            <>
                                                <Plus data-icon="inline-start" />
                                                {t('Ekle')}
                                            </>
                                        )}
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                ) : null}
            </div>
        </SectionCard>
    );
}
