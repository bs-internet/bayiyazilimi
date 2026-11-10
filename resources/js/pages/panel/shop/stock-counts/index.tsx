import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, Play, RotateCcw, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index as stockIndex } from '@/routes/panel/shop/stock';
import { index, show, store } from '@/routes/panel/shop/stock-counts';
import type { Paginated, ShopOption } from '@/types';

type StockCountStatus = 'open' | 'completed' | 'cancelled';

const STOCK_COUNT_BADGE: Record<
    StockCountStatus,
    'default' | 'secondary' | 'outline'
> = {
    open: 'default',
    completed: 'secondary',
    cancelled: 'outline',
};

type CountRow = {
    id: string;
    number: string;
    warehouse: string | null;
    location: string | null;
    status: StockCountStatus;
    status_label: string;
    lines: number;
    movements: number;
    note: string | null;
    user: string | null;
    closer: string | null;
    created_at: string;
    closed_at: string | null;
};

type LocationOption = { id: string; warehouse_id: string; code: string };

type Props = {
    counts: Paginated<CountRow>;
    filters: { status?: string; warehouse?: string };
    statuses: ShopOption[];
    warehouses: ShopOption[];
    activeWarehouses: ShopOption[];
    defaultWarehouseId: string | null;
    locations: LocationOption[];
};

function StartDialog({
    open,
    onOpenChange,
    warehouses,
    defaultWarehouseId,
    locations,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    warehouses: ShopOption[];
    defaultWarehouseId: string | null;
    locations: LocationOption[];
}) {
    const form = useForm({
        warehouse_id: defaultWarehouseId ?? warehouses[0]?.value ?? '',
        location_id: '',
        note: '',
    });

    const locationOptions = locations
        .filter((l) => l.warehouse_id === form.data.warehouse_id)
        .map((l) => ({ value: l.id, label: l.code }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(), { preserveScroll: true });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{t('Sayım başlat')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Ürünleri barkodla okutarak sayın; onaylayınca farklar stoğa "Sayım Düzeltme" olarak işlenir.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="my-5">
                        <Field>
                            <FieldLabel>{t('Depo')}</FieldLabel>
                            <SearchSelect
                                aria-label={t('Depo')}
                                value={form.data.warehouse_id}
                                onChange={(v) =>
                                    form.setData((d) => ({
                                        ...d,
                                        warehouse_id: v,
                                        location_id: '',
                                    }))
                                }
                                options={warehouses}
                            />
                            <InputError message={form.errors.warehouse_id} />
                        </Field>
                        <Field>
                            <FieldLabel>{t('Kapsam')}</FieldLabel>
                            <SearchSelect
                                aria-label={t('Lokasyon')}
                                value={form.data.location_id}
                                onChange={(v) => form.setData('location_id', v)}
                                options={locationOptions}
                                placeholder={t('Tüm depo')}
                                clearable
                            />
                            <FieldDescription>
                                {t(
                                    'Boş bırakılırsa tüm depo sayılır. Tek lokasyon (raf) sayımları aynı anda farklı raflarda yürütülebilir.',
                                )}
                            </FieldDescription>
                            <InputError message={form.errors.location_id} />
                        </Field>
                        <Field>
                            <FieldLabel>{t('Not')}</FieldLabel>
                            <Textarea
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                                rows={2}
                                maxLength={500}
                            />
                            <InputError message={form.errors.note} />
                        </Field>
                    </FieldGroup>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            <X data-icon="inline-start" />
                            {t('Vazgeç')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Play data-icon="inline-start" />
                            )}
                            {t('Başlat')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function StockCountIndex({
    counts,
    filters,
    statuses,
    warehouses,
    activeWarehouses,
    defaultWarehouseId,
    locations,
}: Props) {
    const [starting, setStarting] = useState(false);
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

    return (
        <>
            <Head title={t('Sayım')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu'), href: stockIndex() },
                        { title: t('Sayım') },
                    ]}
                    title={t('Sayım')}
                    meta={t(':total oturum', { total: counts.total })}
                    description={t(
                        'Depo ya da raf bazında sayım oturumları. Sayılan miktarlar onaylanınca farklar toplu olarak stoğa işlenir.',
                    )}
                    actions={
                        <Button onClick={() => setStarting(true)}>
                            <Play data-icon="inline-start" />
                            {t('Sayım başlat')}
                        </Button>
                    }
                />

                <TableCard
                    toolbar={
                        <div className="grid w-full gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <SearchSelect
                                aria-label={t('Durum')}
                                value={filters.status ?? ''}
                                onChange={(v) => apply({ status: v })}
                                options={statuses}
                                placeholder={t('Tüm durumlar')}
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
                            {hasFilter && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get(index.url())}
                                >
                                    <RotateCcw data-icon="inline-start" />
                                    {t('Temizle')}
                                </Button>
                            )}
                        </div>
                    }
                    footer={
                        counts.data.length > 0 ? (
                            <DataPagination
                                paginator={counts}
                                label={t('oturum')}
                            />
                        ) : undefined
                    }
                >
                    {counts.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ClipboardCheck />
                                </EmptyMedia>
                                <EmptyTitle>{t('Sayım yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        '"Sayım başlat" ile bir depo ya da raf için sayım oturumu açın.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Sayım')}</TableHead>
                                    <TableHead>{t('Depo / Kapsam')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Satır / Düzeltme')}
                                    </TableHead>
                                    <TableHead>{t('Kullanıcı')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {counts.data.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell>
                                            <Link
                                                href={show(c.id)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                {c.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDateTime(c.created_at)}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div>{c.warehouse}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {c.location ?? t('Tüm depo')}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    STOCK_COUNT_BADGE[c.status]
                                                }
                                            >
                                                {c.status_label}
                                            </Badge>
                                            {c.closed_at && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        c.closed_at,
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {c.lines} /{' '}
                                            {c.status === 'completed'
                                                ? c.movements
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <div>{c.user ?? '—'}</div>
                                            {c.closer && (
                                                <div className="text-muted-foreground">
                                                    {t('Kapatan: :closer', {
                                                        closer: c.closer,
                                                    })}
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>

            {starting && (
                <StartDialog
                    open={starting}
                    onOpenChange={setStarting}
                    warehouses={activeWarehouses}
                    defaultWarehouseId={defaultWarehouseId}
                    locations={locations}
                />
            )}
        </>
    );
}

StockCountIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Sayım'), href: index() },
    ],
};
