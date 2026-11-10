import { ArrowDown, ArrowUp, Plus, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { search } from '@/routes/panel/shop/products';
import type { ProductPick } from '@/types';

/**
 * Ürün seçici: SKU/ad ile arar, seçilenleri sıralanabilir liste olarak tutar.
 */
export function ProductPicker({
    value,
    onChange,
}: {
    value: ProductPick[];
    onChange: (next: ProductPick[]) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ProductPick[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(search.url({ query: { q: term } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => r.json())
                .then((json: { data: ProductPick[] }) => setResults(json.data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const selectedIds = new Set(value.map((p) => p.id));
    const visibleResults = query.trim().length < 2 ? [] : results;

    const moveItem = (index: number, delta: number) => {
        const next = [...value];
        const target = index + delta;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <div className="grid gap-3 md:grid-cols-2">
            <div className="flex flex-col gap-2">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t(
                            'Ürün adı veya SKU ile ara (en az 2 karakter)',
                        )}
                        className="pl-8"
                    />
                </div>
                <div className="max-h-72 overflow-y-auto rounded-md border">
                    {loading ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            {t('Aranıyor…')}
                        </p>
                    ) : visibleResults.length === 0 ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            {t('Sonuç yok.')}
                        </p>
                    ) : (
                        visibleResults.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                disabled={selectedIds.has(p.id)}
                                onClick={() => onChange([...value, p])}
                                className="flex w-full cursor-pointer items-center gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-accent disabled:cursor-default disabled:opacity-50"
                            >
                                <Plus className="size-4 shrink-0" />
                                <span className="flex-1">
                                    {p.name}
                                    <span className="block text-xs text-muted-foreground">
                                        {p.sku}
                                    </span>
                                </span>
                            </button>
                        ))
                    )}
                </div>
            </div>
            <div className="flex flex-col gap-2">
                <p className="text-sm font-medium">
                    {t('Seçilen ürünler (:value_length)', {
                        value_length: value.length,
                    })}
                </p>
                <div className="max-h-80 overflow-y-auto rounded-md border">
                    {value.length === 0 ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            {t('Henüz ürün seçilmedi.')}
                        </p>
                    ) : (
                        value.map((p, index) => (
                            <div
                                key={p.id}
                                className="flex items-center gap-1 border-b px-2 py-1.5 text-sm last:border-b-0"
                            >
                                <span className="w-6 text-center text-xs text-muted-foreground">
                                    {index + 1}
                                </span>
                                <span className="flex-1">
                                    {p.name}
                                    <span className="block text-xs text-muted-foreground">
                                        {p.sku}
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('Yukarı')}
                                    disabled={index === 0}
                                    onClick={() => moveItem(index, -1)}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('Aşağı')}
                                    disabled={index === value.length - 1}
                                    onClick={() => moveItem(index, 1)}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('Çıkar')}
                                    onClick={() =>
                                        onChange(
                                            value.filter((x) => x.id !== p.id),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
