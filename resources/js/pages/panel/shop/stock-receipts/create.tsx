import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    PackagePlus,
    Plus,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import {
    create,
    index,
    products as productSearch,
    store,
} from '@/routes/panel/shop/stock-receipts';
import { index as suppliersIndex } from '@/routes/panel/shop/suppliers';
import type { ShopOption } from '@/types';

type Tracking = 'none' | 'lot' | 'serial';

type ProductHit = {
    id: string;
    sku: string;
    name: string;
    unit: string;
    tracking: Tracking;
    expiry_tracking: boolean;
};

type Line = {
    key: string;
    product: ProductHit;
    product_id: string;
    quantity: string;
    location_id: string;
    lot_code: string;
    expires_at: string;
    serials: string;
};

type LocationOption = { id: string; warehouse_id: string; code: string };

type Props = {
    suppliers: ShopOption[];
    warehouses: ShopOption[];
    defaultWarehouseId: string | null;
    documentTypes: ShopOption[];
    locations: LocationOption[];
    maxLines: number;
};

const today = () => new Date().toISOString().slice(0, 10);

const serialCount = (raw: string) =>
    raw.split(/[\s,;]+/).filter((s) => s.trim() !== '').length;

function ProductSearch({ onPick }: { onPick: (p: ProductHit) => void }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ProductHit[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(productSearch.url({ query: { q: term } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => r.json())
                .then((json: { data: ProductHit[] }) => setResults(json.data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const visible = query.trim().length < 2 ? [] : results;

    return (
        <div className="flex flex-col gap-2">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={t(
                        'Satır eklemek için ürün adı, SKU ya da barkod yazın',
                    )}
                    className="pl-8"
                    aria-label={t('Ürün ara')}
                />
            </div>
            {query.trim().length >= 2 && (
                <div className="max-h-64 overflow-y-auto rounded-md border">
                    {loading ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            {t('Aranıyor…')}
                        </p>
                    ) : visible.length === 0 ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            {t('Sonuç yok.')}
                        </p>
                    ) : (
                        visible.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => {
                                    onPick(p);
                                    setQuery('');
                                }}
                                className="flex w-full cursor-pointer items-center gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-accent"
                            >
                                <Plus className="size-4 shrink-0" />
                                <span className="flex-1">
                                    {p.name}
                                    <span className="block text-xs text-muted-foreground">
                                        {p.sku}
                                        {p.tracking === 'lot' &&
                                            t(' · Lot takipli')}
                                        {p.tracking === 'serial' &&
                                            t(' · Seri no takipli')}
                                        {p.expiry_tracking && t(' · SKT')}
                                    </span>
                                </span>
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function StockReceiptCreate({
    suppliers,
    warehouses,
    defaultWarehouseId,
    documentTypes,
    locations,
    maxLines,
}: Props) {
    const form = useForm<{
        supplier_id: string;
        document_type: string;
        document_no: string;
        document_date: string;
        warehouse_id: string;
        note: string;
        lines: Line[];
    }>({
        supplier_id: '',
        document_type: documentTypes[0]?.value ?? 'waybill',
        document_no: '',
        document_date: today(),
        warehouse_id: defaultWarehouseId ?? warehouses[0]?.value ?? '',
        note: '',
        lines: [],
    });
    const { data, setData, errors, processing } = form;
    const errorFor = (i: number, field: string) =>
        (errors as Record<string, string | undefined>)[`lines.${i}.${field}`];

    const locationOptions = locations
        .filter((l) => l.warehouse_id === data.warehouse_id)
        .map((l) => ({ value: l.id, label: l.code }));

    const addLine = (product: ProductHit) =>
        setData('lines', [
            ...data.lines,
            {
                key: `${product.id}-${Date.now()}`,
                product,
                product_id: product.id,
                quantity: '1',
                location_id: '',
                lot_code: '',
                expires_at: '',
                serials: '',
            },
        ]);

    const updateLine = (index: number, patch: Partial<Line>) =>
        setData(
            'lines',
            data.lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    const removeLine = (index: number) =>
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((d) => ({
            ...d,
            lines: d.lines.map((line) => ({
                product_id: line.product_id,
                quantity:
                    line.product.tracking === 'serial'
                        ? String(serialCount(line.serials))
                        : line.quantity,
                location_id: line.location_id || null,
                lot_code: line.lot_code || null,
                expires_at: line.expires_at || null,
                serials: line.serials || null,
            })),
        }));
        form.post(store.url(), { preserveScroll: true });
    };

    const totalQuantity = data.lines.reduce(
        (sum, line) =>
            sum +
            (line.product.tracking === 'serial'
                ? serialCount(line.serials)
                : Number(line.quantity) || 0),
        0,
    );

    return (
        <>
            <Head title={t('Yeni Mal Kabul')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Mal Kabul'), href: index() },
                        { title: t('Yeni') },
                    ]}
                    title={t('Yeni Mal Kabul')}
                    description={t(
                        'Tedarikçinin irsaliye / faturasındaki ürünleri stoğa girin. Lot / seri takipli ürünlerde lot no ya da seri numaraları, SKT takipli ürünlerde son kullanma tarihi zorunludur.',
                    )}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                <ArrowLeft data-icon="inline-start" />
                                {t('Mal kabuller')}
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <SectionCard title={t('Belge')}>
                        <FieldGroup>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(errors.supplier_id)}
                                >
                                    <FieldLabel>{t('Tedarikçi')}</FieldLabel>
                                    <SearchSelect
                                        value={data.supplier_id}
                                        onChange={(v) =>
                                            setData('supplier_id', v)
                                        }
                                        options={suppliers}
                                        placeholder={
                                            suppliers.length
                                                ? t('Tedarikçi seçin')
                                                : t('Önce tedarikçi tanımlayın')
                                        }
                                        required
                                    />
                                    {suppliers.length === 0 && (
                                        <FieldDescription>
                                            <Link
                                                href={suppliersIndex()}
                                                className="underline"
                                            >
                                                {t(
                                                    'Tanımlamalar › Tedarikçiler',
                                                )}
                                            </Link>{' '}
                                            {t('ekranından ekleyin.')}
                                        </FieldDescription>
                                    )}
                                    <InputError message={errors.supplier_id} />
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.warehouse_id)}
                                >
                                    <FieldLabel>{t('Giriş deposu')}</FieldLabel>
                                    <SearchSelect
                                        value={data.warehouse_id}
                                        onChange={(v) => {
                                            setData((d) => ({
                                                ...d,
                                                warehouse_id: v,
                                                lines: d.lines.map((l) => ({
                                                    ...l,
                                                    location_id: '',
                                                })),
                                            }));
                                        }}
                                        options={warehouses}
                                        required
                                    />
                                    <InputError message={errors.warehouse_id} />
                                </Field>
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    data-invalid={Boolean(errors.document_type)}
                                >
                                    <FieldLabel>{t('Belge türü')}</FieldLabel>
                                    <SearchSelect
                                        value={data.document_type}
                                        onChange={(v) =>
                                            setData('document_type', v)
                                        }
                                        options={documentTypes}
                                        required
                                    />
                                    <InputError
                                        message={errors.document_type}
                                    />
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.document_no)}
                                >
                                    <FieldLabel htmlFor="document_no">
                                        {t('Belge no')}
                                    </FieldLabel>
                                    <Input
                                        id="document_no"
                                        value={data.document_no}
                                        onChange={(e) =>
                                            setData(
                                                'document_no',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={t('Örn. ABC2026000123')}
                                        required
                                    />
                                    <InputError message={errors.document_no} />
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.document_date)}
                                >
                                    <FieldLabel htmlFor="document_date">
                                        {t('Belge tarihi')}
                                    </FieldLabel>
                                    <Input
                                        id="document_date"
                                        type="date"
                                        max={today()}
                                        value={data.document_date}
                                        onChange={(e) =>
                                            setData(
                                                'document_date',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.document_date}
                                    />
                                </Field>
                            </div>
                            <Field data-invalid={Boolean(errors.note)}>
                                <FieldLabel htmlFor="note">
                                    {t('Not')}
                                </FieldLabel>
                                <Textarea
                                    id="note"
                                    rows={2}
                                    value={data.note}
                                    onChange={(e) =>
                                        setData('note', e.target.value)
                                    }
                                />
                                <InputError message={errors.note} />
                            </Field>
                        </FieldGroup>
                    </SectionCard>

                    <SectionCard
                        title={t('Satırlar (:lines_length)', {
                            lines_length: data.lines.length,
                        })}
                        description={
                            data.lines.length > 0
                                ? t('Toplam :total_quantity adet', {
                                      total_quantity: totalQuantity,
                                  })
                                : undefined
                        }
                    >
                        <div className="flex flex-col gap-4">
                            {data.lines.length < maxLines && (
                                <ProductSearch onPick={addLine} />
                            )}
                            <InputError message={errors.lines} />

                            {data.lines.map((line, i) => {
                                const tracking = line.product.tracking;

                                return (
                                    <div
                                        key={line.key}
                                        className="flex flex-col gap-3 rounded-lg border p-3"
                                    >
                                        <div className="flex items-start gap-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="font-medium">
                                                    {line.product.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    <span className="font-mono">
                                                        {line.product.sku}
                                                    </span>
                                                    {tracking === 'lot' &&
                                                        t(' · Lot takipli')}
                                                    {tracking === 'serial' &&
                                                        t(' · Seri no takipli')}
                                                    {line.product
                                                        .expiry_tracking &&
                                                        t(' · SKT takipli')}
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t('Satırı sil')}
                                                onClick={() => removeLine(i)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                            {tracking !== 'serial' && (
                                                <Field
                                                    data-invalid={Boolean(
                                                        errorFor(i, 'quantity'),
                                                    )}
                                                >
                                                    <FieldLabel>
                                                        {t('Miktar ( :unit)', {
                                                            unit: line.product
                                                                .unit,
                                                        })}
                                                    </FieldLabel>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        value={line.quantity}
                                                        onChange={(e) =>
                                                            updateLine(i, {
                                                                quantity:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={errorFor(
                                                            i,
                                                            'quantity',
                                                        )}
                                                    />
                                                </Field>
                                            )}
                                            <Field
                                                data-invalid={Boolean(
                                                    errorFor(i, 'location_id'),
                                                )}
                                            >
                                                <FieldLabel>
                                                    {t('Lokasyon')}
                                                </FieldLabel>
                                                <SearchSelect
                                                    key={`loc-${data.warehouse_id}-${line.key}`}
                                                    value={line.location_id}
                                                    onChange={(v) =>
                                                        updateLine(i, {
                                                            location_id: v,
                                                        })
                                                    }
                                                    options={locationOptions}
                                                    placeholder={t('Otomatik')}
                                                    clearable
                                                />
                                                <InputError
                                                    message={errorFor(
                                                        i,
                                                        'location_id',
                                                    )}
                                                />
                                            </Field>
                                            {tracking === 'lot' && (
                                                <Field
                                                    data-invalid={Boolean(
                                                        errorFor(i, 'lot_code'),
                                                    )}
                                                >
                                                    <FieldLabel>
                                                        {t('Lot no')}
                                                    </FieldLabel>
                                                    <Input
                                                        value={line.lot_code}
                                                        onChange={(e) =>
                                                            updateLine(i, {
                                                                lot_code:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={errorFor(
                                                            i,
                                                            'lot_code',
                                                        )}
                                                    />
                                                </Field>
                                            )}
                                            {tracking !== 'none' && (
                                                <Field
                                                    data-invalid={Boolean(
                                                        errorFor(
                                                            i,
                                                            'expires_at',
                                                        ),
                                                    )}
                                                >
                                                    <FieldLabel>
                                                        {t(
                                                            'Son kullanma tarihi',
                                                        )}
                                                        {!line.product
                                                            .expiry_tracking &&
                                                            t(
                                                                ' (isteğe bağlı)',
                                                            )}
                                                    </FieldLabel>
                                                    <Input
                                                        type="date"
                                                        value={line.expires_at}
                                                        onChange={(e) =>
                                                            updateLine(i, {
                                                                expires_at:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                        required={
                                                            line.product
                                                                .expiry_tracking
                                                        }
                                                    />
                                                    <InputError
                                                        message={errorFor(
                                                            i,
                                                            'expires_at',
                                                        )}
                                                    />
                                                </Field>
                                            )}
                                        </div>
                                        {tracking === 'serial' && (
                                            <Field
                                                data-invalid={Boolean(
                                                    errorFor(i, 'serials') ??
                                                    errorFor(i, 'quantity'),
                                                )}
                                            >
                                                <FieldLabel>
                                                    {t(
                                                        'Seri numaraları ( :serials adet)',
                                                        {
                                                            serials:
                                                                serialCount(
                                                                    line.serials,
                                                                ),
                                                        },
                                                    )}
                                                </FieldLabel>
                                                <Textarea
                                                    rows={3}
                                                    value={line.serials}
                                                    onChange={(e) =>
                                                        updateLine(i, {
                                                            serials:
                                                                e.target.value,
                                                        })
                                                    }
                                                    placeholder={t(
                                                        'Her satıra bir seri numarası (barkod okuyucuyla okutabilirsiniz)',
                                                    )}
                                                    className="font-mono"
                                                    required
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'Miktar seri numarası sayısından hesaplanır.',
                                                    )}
                                                </FieldDescription>
                                                <InputError
                                                    message={
                                                        errorFor(
                                                            i,
                                                            'serials',
                                                        ) ??
                                                        errorFor(i, 'quantity')
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </div>
                                );
                            })}

                            {data.lines.length === 0 && (
                                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    <PackagePlus className="size-6" />
                                    {t(
                                        'Henüz satır yok. Yukarıdan ürün arayıp ekleyin.',
                                    )}
                                </div>
                            )}
                        </div>
                    </SectionCard>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={processing || data.lines.length === 0}
                        >
                            {processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Save data-icon="inline-start" />
                            )}
                            {t('Mal kabulü kaydet')}
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}

StockReceiptCreate.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Mal Kabul'), href: index() },
        { title: t('Yeni'), href: create() },
    ],
};
