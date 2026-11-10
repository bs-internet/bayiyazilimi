import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Check,
    CheckCheck,
    Plus,
    ScanBarcode,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';

import {
    InfoList,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/panel';
import {
    approve,
    cancel,
    index,
    lookup,
} from '@/routes/panel/shop/stock-counts';
import {
    destroy as destroyLine,
    store as storeLine,
    update as updateLine,
} from '@/routes/panel/shop/stock-counts/lines';
import type { Paginated } from '@/types';

type Status = 'open' | 'completed' | 'cancelled';

const STATUS_BADGE: Record<Status, 'default' | 'secondary' | 'outline'> = {
    open: 'default',
    completed: 'secondary',
    cancelled: 'outline',
};

type Line = {
    id: string;
    product_id: string;
    sku: string | null;
    barcode: string | null;
    name: string | null;
    unit: string | null;
    serial: boolean;
    location: string | null;
    lot: string | null;
    expires_at: string | null;
    expected: number;
    counted: number;
    difference: number;
    user: string | null;
    updated_at: string;
};

type Uncounted = {
    id: string;
    sku: string | null;
    name: string | null;
    location: string | null;
    lot: string | null;
    quantity: number;
};

type ProductHit = {
    id: string;
    sku: string;
    barcode: string | null;
    name: string;
    unit: string;
    tracking: 'none' | 'lot' | 'serial';
    expiry_tracking: boolean;
    lots: { code: string; expires_at: string | null }[];
};

type Props = {
    count: {
        id: string;
        number: string;
        warehouse: string | null;
        location: string | null;
        status: Status;
        status_label: string;
        zero_uncounted: boolean;
        note: string | null;
        user: string | null;
        closer: string | null;
        created_at: string;
        closed_at: string | null;
    };
    lines: Paginated<Line>;
    summary: {
        lines: number;
        counted: number;
        surplus: number;
        shortage: number;
        differing: number;
        uncounted: number;
        uncounted_quantity: number;
    };
    uncounted: Uncounted[];
    locations: { id: string; warehouse_id: string; code: string }[];
    traceability: boolean;
};

const formatDay = (iso: string) => iso.split('-').reverse().join('.');

function Difference({ value }: { value: number }) {
    return (
        <span
            className={cn(
                'tabular-nums',
                value > 0 && 'text-emerald-600 dark:text-emerald-400',
                value < 0 && 'text-destructive',
                value === 0 && 'text-muted-foreground',
            )}
        >
            {value > 0 ? `+${value}` : value}
        </span>
    );
}

/**
 * Barkod okutma alanı: okutulan kod tek ürüne denk gelirse (lot takipsizse)
 * doğrudan sayıma eklenir; lot / seri takipli üründe lot sorulur; birden çok
 * eşleşmede listeden seçilir.
 */
function Scanner({
    countId,
    locations,
}: {
    countId: string;
    locations: Props['locations'];
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [locationId, setLocationId] = useState(locations[0]?.id ?? '');
    const [code, setCode] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [hits, setHits] = useState<ProductHit[]>([]);
    const [selected, setSelected] = useState<ProductHit | null>(null);
    const [lotCode, setLotCode] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    const reset = () => {
        setCode('');
        setHits([]);
        setSelected(null);
        setLotCode('');
        setExpiresAt('');
        setQuantity('1');
        window.setTimeout(() => inputRef.current?.focus(), 0);
    };

    const record = (product: ProductHit, lot?: string) => {
        const qty = product.tracking === 'serial' ? 1 : Number(quantity) || 1;

        setBusy(true);
        router.post(
            storeLine.url(countId),
            {
                product_id: product.id,
                location_id: locationId,
                lot_code: lot ?? null,
                expires_at: lot && expiresAt ? expiresAt : null,
                quantity: qty,
                mode: 'add',
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setMessage(null);
                    reset();
                },
                onError: (errors) =>
                    setMessage(Object.values(errors)[0] ?? t('Kaydedilemedi.')),
                onFinish: () => setBusy(false),
            },
        );
    };

    const choose = (product: ProductHit) => {
        setHits([]);

        if (product.tracking === 'none') {
            record(product);

            return;
        }

        setSelected(product);
    };

    const search = (event: FormEvent) => {
        event.preventDefault();
        const term = code.trim();

        if (term === '' || busy) {
            return;
        }

        setBusy(true);
        setMessage(null);
        fetch(lookup.url(countId, { query: { q: term } }), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((json: { data: ProductHit[]; exact: boolean }) => {
                setBusy(false);

                if (json.data.length === 0) {
                    setMessage(t('":term" ile eşleşen ürün yok.', { term }));
                    setCode('');

                    return;
                }

                if (json.exact || json.data.length === 1) {
                    choose(json.data[0]);

                    return;
                }

                setHits(json.data);
            })
            .catch(() => {
                setBusy(false);
                setMessage(t('Arama yapılamadı.'));
            });
    };

    const submitLot = (event: FormEvent) => {
        event.preventDefault();

        if (selected && lotCode.trim() !== '') {
            record(selected, lotCode.trim());
        }
    };

    return (
        <SectionCard
            title={t('Okut')}
            description={t(
                "Barkod okuyucuyla ya da SKU / ad yazıp Enter'a basın. Aynı ürün her okutmada adet kadar artar.",
            )}
        >
            <div className="flex flex-col gap-4">
                <div className="grid gap-3 sm:grid-cols-[minmax(0,14rem)_6rem_1fr]">
                    <Field>
                        <FieldLabel>{t('Lokasyon')}</FieldLabel>
                        <SearchSelect
                            aria-label={t('Lokasyon')}
                            value={locationId}
                            onChange={(v) => {
                                setLocationId(v);
                                inputRef.current?.focus();
                            }}
                            options={locations.map((l) => ({
                                value: l.id,
                                label: l.code,
                            }))}
                            disabled={locations.length <= 1}
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="count-qty">{t('Adet')}</FieldLabel>
                        <Input
                            id="count-qty"
                            type="number"
                            min={1}
                            value={quantity}
                            onChange={(e) => setQuantity(e.target.value)}
                        />
                    </Field>
                    <form onSubmit={search}>
                        <Field>
                            <FieldLabel htmlFor="count-code">
                                {t('Barkod / SKU')}
                            </FieldLabel>
                            <div className="flex gap-2">
                                <Input
                                    id="count-code"
                                    ref={inputRef}
                                    value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                    autoFocus
                                    autoComplete="off"
                                    disabled={selected !== null}
                                    placeholder={t('Okutun ya da yazın')}
                                />
                                <Button
                                    type="submit"
                                    disabled={busy || selected !== null}
                                >
                                    {busy ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <ScanBarcode data-icon="inline-start" />
                                    )}
                                    {t('Ekle')}
                                </Button>
                            </div>
                        </Field>
                    </form>
                </div>

                {message && (
                    <p className="text-sm text-destructive">{message}</p>
                )}

                {hits.length > 0 && (
                    <div className="flex flex-col divide-y rounded-md border">
                        {hits.map((h) => (
                            <button
                                key={h.id}
                                type="button"
                                onClick={() => choose(h)}
                                className="flex items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                            >
                                <span>
                                    <span className="font-medium">
                                        {h.name}
                                    </span>
                                    <span className="ml-2 font-mono text-xs text-muted-foreground">
                                        {h.sku}
                                    </span>
                                </span>
                                <Plus className="size-4 text-muted-foreground" />
                            </button>
                        ))}
                    </div>
                )}

                {selected && (
                    <form
                        onSubmit={submitLot}
                        className="flex flex-col gap-3 rounded-md border bg-muted/40 p-3"
                    >
                        <div className="text-sm">
                            <span className="font-medium">{selected.name}</span>{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {selected.sku}
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="count-lot">
                                    {selected.tracking === 'serial'
                                        ? t('Seri no')
                                        : t('Lot no')}
                                </FieldLabel>
                                <Input
                                    id="count-lot"
                                    value={lotCode}
                                    onChange={(e) => setLotCode(e.target.value)}
                                    list="count-lot-options"
                                    autoFocus
                                    autoComplete="off"
                                    maxLength={64}
                                />
                                <datalist id="count-lot-options">
                                    {selected.lots.map((l) => (
                                        <option key={l.code} value={l.code}>
                                            {l.expires_at
                                                ? t('SKT :date', {
                                                      date: formatDay(
                                                          l.expires_at,
                                                      ),
                                                  })
                                                : undefined}
                                        </option>
                                    ))}
                                </datalist>
                            </Field>
                            {selected.expiry_tracking && (
                                <Field>
                                    <FieldLabel htmlFor="count-expiry">
                                        {t('SKT (yeni lot için)')}
                                    </FieldLabel>
                                    <Input
                                        id="count-expiry"
                                        type="date"
                                        value={expiresAt}
                                        onChange={(e) =>
                                            setExpiresAt(e.target.value)
                                        }
                                    />
                                </Field>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={busy || lotCode.trim() === ''}
                            >
                                <Check data-icon="inline-start" />
                                {t('Say')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={reset}
                            >
                                <X data-icon="inline-start" />
                                {t('Vazgeç')}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </SectionCard>
    );
}

function CountedInput({ countId, line }: { countId: string; line: Line }) {
    const [value, setValue] = useState(String(line.counted));

    const save = () => {
        const next = Number(value);

        if (!Number.isInteger(next) || next < 0) {
            setValue(String(line.counted));

            return;
        }

        if (next === line.counted) {
            return;
        }

        router.put(
            updateLine.url({ stockCount: countId, line: line.id }),
            { counted: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setValue(String(line.counted)),
            },
        );
    };

    return (
        <Input
            type="number"
            min={0}
            max={line.serial ? 1 : undefined}
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onBlur={save}
            onKeyDown={(e: KeyboardEvent<HTMLInputElement>) => {
                if (e.key === 'Enter') {
                    e.currentTarget.blur();
                }
            }}
            className="ml-auto h-8 w-20 text-right tabular-nums"
            aria-label={t('Sayılan')}
        />
    );
}

function ApproveDialog({
    countId,
    summary,
}: {
    countId: string;
    summary: Props['summary'];
}) {
    const [open, setOpen] = useState(false);
    const [zero, setZero] = useState(false);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            approve.url(countId),
            { zero_uncounted: zero },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Button
                onClick={() => setOpen(true)}
                disabled={summary.lines === 0 && summary.uncounted === 0}
            >
                <CheckCheck data-icon="inline-start" />
                {t('Onayla')}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Sayımı onayla')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Farklar "Sayım Düzeltme" hareketi olarak stoğa işlenir ve sayım kapanır. Bu işlem geri alınamaz.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="my-2">
                        <InfoList
                            items={[
                                {
                                    label: t('Sayılan satır'),
                                    value: summary.lines,
                                },
                                {
                                    label: t('Farklı satır'),
                                    value: summary.differing,
                                },
                                {
                                    label: t('Fazla'),
                                    value: (
                                        <Difference value={summary.surplus} />
                                    ),
                                },
                                {
                                    label: t('Eksik'),
                                    value: (
                                        <Difference value={-summary.shortage} />
                                    ),
                                },
                            ]}
                        />
                        {summary.uncounted > 0 && (
                            <div className="flex items-start gap-3 rounded-md border p-3">
                                <Checkbox
                                    id="zero-uncounted"
                                    checked={zero}
                                    onCheckedChange={(v) => setZero(v === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="zero-uncounted">
                                        {t('Sayılmayanları sıfırla')}
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            "Kapsamda stoğu görünüp sayılmayan :uncounted satır ( :uncounted_quantity adet) 0'a çekilir. Yalnız tam sayımda işaretleyin.",
                                            {
                                                uncounted: summary.uncounted,
                                                uncounted_quantity:
                                                    summary.uncounted_quantity,
                                            },
                                        )}
                                    </p>
                                </div>
                            </div>
                        )}
                    </FieldGroup>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={processing}
                        >
                            <X data-icon="inline-start" />
                            {t('Vazgeç')}
                        </Button>
                        <Button onClick={submit} disabled={processing}>
                            {processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <CheckCheck data-icon="inline-start" />
                            )}
                            {t('Onayla ve stoğa işle')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function StockCountShow({
    count,
    lines,
    summary,
    uncounted,
    locations,
    traceability,
}: Props) {
    const isOpen = count.status === 'open';
    const showLots = traceability || lines.data.some((l) => l.lot);

    return (
        <>
            <Head title={t('Sayım :number', { number: count.number })} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Sayım'), href: index() },
                        { title: count.number },
                    ]}
                    title={t('Sayım :number', { number: count.number })}
                    badges={
                        <Badge variant={STATUS_BADGE[count.status]}>
                            {count.status_label}
                        </Badge>
                    }
                    meta={`${count.warehouse ?? ''} · ${count.location ?? t('Tüm depo')}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={index()}>
                                    <ArrowLeft data-icon="inline-start" />
                                    {t('Sayımlar')}
                                </Link>
                            </Button>
                            {isOpen && (
                                <ConfirmDelete
                                    action={cancel(count.id)}
                                    title={t('Sayım iptal edilsin mi?')}
                                    description={t(
                                        'Sayılan satırlar stoğa işlenmez; sayım kapanır.',
                                    )}
                                    confirmLabel={t('İptal et')}
                                    confirmIcon={Ban}
                                >
                                    <Button variant="outline">
                                        <Ban data-icon="inline-start" />
                                        {t('İptal et')}
                                    </Button>
                                </ConfirmDelete>
                            )}
                            {isOpen && (
                                <ApproveDialog
                                    countId={count.id}
                                    summary={summary}
                                />
                            )}
                        </div>
                    }
                />

                <SectionCard>
                    <div className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                        <Stat
                            label={t('Sayılan satır')}
                            value={summary.lines}
                        />
                        <Stat
                            label={t('Sayılan adet')}
                            value={summary.counted}
                        />
                        <Stat
                            label={t('Fazla')}
                            value={<Difference value={summary.surplus} />}
                        />
                        <Stat
                            label={t('Eksik')}
                            value={<Difference value={-summary.shortage} />}
                        />
                        <Stat
                            label={isOpen ? t('Başlatan') : t('Kapatan')}
                            value={
                                <>
                                    {isOpen ? count.user : count.closer}
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        {formatDateTime(
                                            isOpen
                                                ? count.created_at
                                                : (count.closed_at ??
                                                      count.created_at),
                                        )}
                                    </span>
                                </>
                            }
                        />
                    </div>
                    {count.note && (
                        <p className="mt-4 text-sm text-muted-foreground">
                            {count.note}
                        </p>
                    )}
                    {count.status === 'completed' && count.zero_uncounted && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t('Onayda sayılmayan stok satırları sıfırlandı.')}
                        </p>
                    )}
                </SectionCard>

                {isOpen && <Scanner countId={count.id} locations={locations} />}

                <SectionCard
                    flush
                    title={t('Sayılanlar')}
                    footer={
                        lines.data.length > 0 ? (
                            <DataPagination
                                paginator={lines}
                                label={t('satır')}
                            />
                        ) : undefined
                    }
                    description={
                        isOpen
                            ? t(
                                  'Beklenen, satırın son güncellendiği andaki sistem miktarıdır; onayda fark kadar düzeltilir.',
                              )
                            : undefined
                    }
                >
                    {lines.data.length === 0 ? (
                        <p className="px-5 py-8 text-center text-sm text-muted-foreground">
                            {t('Henüz sayılan ürün yok.')}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Lokasyon')}</TableHead>
                                    {showLots && (
                                        <TableHead>{t('Lot / Seri')}</TableHead>
                                    )}
                                    <TableHead className="text-right">
                                        {t('Beklenen')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Sayılan')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Fark')}
                                    </TableHead>
                                    {isOpen && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.data.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {l.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {l.sku}
                                                {l.barcode
                                                    ? ` · ${l.barcode}`
                                                    : ''}
                                            </div>
                                        </TableCell>
                                        <TableCell>{l.location}</TableCell>
                                        {showLots && (
                                            <TableCell>
                                                {l.lot ? (
                                                    <>
                                                        <span className="font-mono">
                                                            {l.lot}
                                                        </span>
                                                        {l.expires_at && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {t(
                                                                    'SKT :expires_at',
                                                                    {
                                                                        expires_at:
                                                                            formatDay(
                                                                                l.expires_at,
                                                                            ),
                                                                    },
                                                                )}
                                                            </div>
                                                        )}
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right tabular-nums">
                                            {l.expected}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {isOpen ? (
                                                <CountedInput
                                                    key={`${l.id}-${l.counted}`}
                                                    countId={count.id}
                                                    line={l}
                                                />
                                            ) : (
                                                l.counted
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Difference value={l.difference} />
                                        </TableCell>
                                        {isOpen && (
                                            <TableCell className="text-right">
                                                <ConfirmDelete
                                                    action={destroyLine({
                                                        stockCount: count.id,
                                                        line: l.id,
                                                    })}
                                                    title={t(
                                                        'Satır silinsin mi?',
                                                    )}
                                                    description={t(
                                                        ':l_name (:location) sayımdan çıkarılır.',
                                                        {
                                                            l_name: l.name,
                                                            location:
                                                                l.location,
                                                        },
                                                    )}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'Satırı sil',
                                                        )}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </ConfirmDelete>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </SectionCard>

                {isOpen && summary.uncounted > 0 && (
                    <SectionCard
                        flush
                        title={t('Sayılmayanlar (:uncounted)', {
                            uncounted: summary.uncounted,
                        })}
                        description={
                            summary.uncounted > uncounted.length
                                ? t(
                                      'Kapsamda sistemde stoğu görünüp henüz sayılmamış satırlar; ilk :uncounted_length satır gösteriliyor. Onayda istenirse tamamı sıfırlanır.',
                                      { uncounted_length: uncounted.length },
                                  )
                                : t(
                                      'Kapsamda sistemde stoğu görünüp henüz sayılmamış satırlar. Onayda istenirse sıfırlanır.',
                                  )
                        }
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Lokasyon')}</TableHead>
                                    {showLots && (
                                        <TableHead>{t('Lot / Seri')}</TableHead>
                                    )}
                                    <TableHead className="text-right">
                                        {t('Sistem')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {uncounted.map((u) => (
                                    <TableRow key={u.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {u.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {u.sku}
                                            </div>
                                        </TableCell>
                                        <TableCell>{u.location}</TableCell>
                                        {showLots && (
                                            <TableCell className="font-mono">
                                                {u.lot ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right tabular-nums">
                                            {u.quantity}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </SectionCard>
                )}
            </Page>
        </>
    );
}

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-lg font-semibold">{value}</div>
        </div>
    );
}

StockCountShow.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Sayım'), href: index() },
    ],
};
