import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    Boxes,
    FileSpreadsheet,
    History,
    LayoutGrid,
    MapPin,
    Package,
    Pencil,
    Plus,
    Save,
    Search,
} from 'lucide-react';
import { useState } from 'react';

import { ActiveBadge } from '@/components/admin/active-badge';
import {
    InfoList,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
    TableCard,
} from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { StatusSwitch, TextField } from '@/components/shop/form-fields';
import { MovementReference } from '@/components/shop/movement-reference';
import { StockMovementBadge } from '@/components/shop/stock-movement-badge';
import { StockOperationForm } from '@/components/shop/stock-operation-form';
import type {
    StockLocationOption,
    StockLocationQuantity,
    StockWarehouseOption,
} from '@/components/shop/stock-operation-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { FieldDescription, FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
import { edit as productEdit } from '@/routes/panel/shop/products';
import { movements as stockMovements } from '@/routes/panel/shop/stock';
import {
    bulk as locationBulk,
    destroy as locationDestroy,
    status as locationStatus,
    store as locationStore,
    update as locationUpdate,
} from '@/routes/panel/shop/warehouse-locations';
import { exportMethod, index, show } from '@/routes/panel/shop/warehouses';
import type {
    Paginated,
    ShopWarehouse,
    Status,
    StockMovementItem,
} from '@/types';

type StockRow = {
    product_id: string;
    sku: string | null;
    name: string | null;
    image: string | null;
    category: string | null;
    quantity: number;
    tracking: 'none' | 'lot' | 'serial';
    expiry_tracking: boolean;
    quantities: Record<string, number>;
    locations: StockLocationQuantity[];
};

type LocationRow = {
    id: string;
    code: string;
    aisle: string | null;
    rack: string | null;
    bin: string | null;
    is_unassigned: boolean;
    status: Status;
    products: number;
    quantity: number;
};

type Props = {
    warehouse: ShopWarehouse;
    stocks: Paginated<StockRow>;
    movements: Paginated<StockMovementItem>;
    locations: LocationRow[];
    allWarehouses: StockWarehouseOption[];
    allLocations: StockLocationOption[];
    totals: {
        products: number;
        quantity: number;
        movements: number;
        locations: number;
    };
    q: string;
    locationFilter: string | null;
};

type LocationSheetState =
    | { mode: 'closed' }
    | { mode: 'single'; location: LocationRow | null }
    | { mode: 'bulk' };

/** Tek lokasyon ekleme / düzenleme ya da toplu oluşturma paneli. */
function LocationSheet({
    state,
    warehouseId,
    onClose,
}: {
    state: LocationSheetState;
    warehouseId: string;
    onClose: () => void;
}) {
    const open = state.mode !== 'closed';
    const location = state.mode === 'single' ? state.location : null;
    const isBulk = state.mode === 'bulk';

    return (
        <Sheet open={open} onOpenChange={(v) => (v ? null : onClose())}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {isBulk
                            ? t('Toplu lokasyon oluştur')
                            : location
                              ? t(':code düzenle', { code: location.code })
                              : t('Lokasyon ekle')}
                    </SheetTitle>
                    <SheetDescription>
                        {isBulk
                            ? t(
                                  'Koridorlar × raf aralığı × göz aralığı kadar lokasyon açılır; var olanlar atlanır.',
                              )
                            : t(
                                  'Kod koridor-raf-göz biçiminde oluşur (ör. A-03-2).',
                              )}
                    </SheetDescription>
                </SheetHeader>
                {open ? (
                    <Form
                        key={isBulk ? 'bulk' : (location?.id ?? 'new')}
                        {...(isBulk
                            ? locationBulk.form(warehouseId)
                            : location
                              ? locationUpdate.form(location.id)
                              : locationStore.form(warehouseId))}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {isBulk ? (
                                    <FieldGroup>
                                        <TextField
                                            name="aisles"
                                            label={t('Koridorlar')}
                                            placeholder="A, B, C"
                                            required
                                            error={errors.aisles}
                                            description={t(
                                                'Virgül ya da boşlukla ayırın.',
                                            )}
                                        />
                                        <div className="grid grid-cols-2 gap-3">
                                            <TextField
                                                name="rack_from"
                                                label={t('İlk raf')}
                                                type="number"
                                                min={0}
                                                defaultValue={1}
                                                required
                                                error={errors.rack_from}
                                            />
                                            <TextField
                                                name="rack_to"
                                                label={t('Son raf')}
                                                type="number"
                                                min={0}
                                                defaultValue={10}
                                                required
                                                error={errors.rack_to}
                                            />
                                            <TextField
                                                name="bin_from"
                                                label={t('İlk göz')}
                                                type="number"
                                                min={0}
                                                error={errors.bin_from}
                                            />
                                            <TextField
                                                name="bin_to"
                                                label={t('Son göz')}
                                                type="number"
                                                min={0}
                                                error={errors.bin_to}
                                            />
                                        </div>
                                        <FieldDescription>
                                            {t(
                                                'Göz boş bırakılırsa lokasyonlar koridor-raf düzeyinde açılır (A-01, A-02 …).',
                                            )}
                                        </FieldDescription>
                                    </FieldGroup>
                                ) : (
                                    <FieldGroup>
                                        <div className="grid grid-cols-3 gap-3">
                                            <TextField
                                                name="aisle"
                                                label={t('Koridor')}
                                                placeholder="A"
                                                defaultValue={location?.aisle}
                                                error={errors.aisle}
                                            />
                                            <TextField
                                                name="rack"
                                                label={t('Raf')}
                                                placeholder="03"
                                                defaultValue={location?.rack}
                                                error={errors.rack}
                                            />
                                            <TextField
                                                name="bin"
                                                label={t('Göz')}
                                                placeholder="2"
                                                defaultValue={location?.bin}
                                                error={errors.bin}
                                            />
                                        </div>
                                    </FieldGroup>
                                )}
                                <FormActions
                                    destroy={
                                        location && (
                                            <DeleteButton
                                                action={locationDestroy(
                                                    location.id,
                                                )}
                                                title={t(':code silinsin mi?', {
                                                    code: location.code,
                                                })}
                                                description={t(
                                                    'Yalnızca stoğu olmayan lokasyon silinebilir.',
                                                )}
                                                disabled={
                                                    location.quantity !== 0
                                                }
                                                onSuccess={onClose}
                                            />
                                        )
                                    }
                                >
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {isBulk ? t('Oluştur') : t('Kaydet')}
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

export default function WarehouseShow({
    warehouse,
    stocks,
    movements,
    locations,
    allWarehouses,
    allLocations,
    totals,
    q,
    locationFilter,
}: Props) {
    const [locationSheet, setLocationSheet] = useState<LocationSheetState>({
        mode: 'closed',
    });
    const [operation, setOperation] = useState<{
        row: StockRow | null;
        key: number;
    }>({ row: null, key: 0 });

    const filterByLocation = (id: string | null) =>
        router.get(
            show.url(warehouse.id),
            { ...(q ? { q } : {}), ...(id ? { lokasyon: id } : {}) },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title={warehouse.name} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Depolar'), href: index() },
                        { title: warehouse.name },
                    ]}
                    title={warehouse.name}
                    badges={
                        <>
                            <ActiveBadge
                                active={warehouse.status === 'active'}
                            />
                            {warehouse.is_default && (
                                <Badge variant="secondary">
                                    {t('Varsayılan')}
                                </Badge>
                            )}
                            {!warehouse.is_sellable && (
                                <Badge variant="outline">
                                    {t('Satışa kapalı')}
                                </Badge>
                            )}
                        </>
                    }
                    meta={
                        <>
                            <span>
                                {t('Kod:')}{' '}
                                <span className="font-mono">
                                    {warehouse.code}
                                </span>
                            </span>
                            <MetaDot />
                            <span>
                                {t(':count lokasyon', {
                                    count: totals.locations,
                                })}
                            </span>
                            <MetaDot />
                            <span>
                                {t(':products ürün', {
                                    products: totals.products,
                                })}
                            </span>
                            <MetaDot />
                            <span>
                                {t(':count adet', { count: totals.quantity })}
                            </span>
                        </>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={index()}>
                                    <ArrowLeft data-icon="inline-start" />
                                    {t('Depolar')}
                                </Link>
                            </Button>
                            <Button asChild>
                                {/* İndirme: Inertia değil, düz bağlantı. */}
                                <a href={exportMethod.url(warehouse.id)}>
                                    <FileSpreadsheet data-icon="inline-start" />
                                    {t("Excel'e aktar")}
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <TableCard
                        toolbar={
                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    <MapPin className="size-4 text-muted-foreground" />
                                    {t('Lokasyonlar')}
                                </div>
                                <div className="ml-auto flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setLocationSheet({ mode: 'bulk' })
                                        }
                                    >
                                        <LayoutGrid data-icon="inline-start" />
                                        {t('Toplu oluştur')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setLocationSheet({
                                                mode: 'single',
                                                location: null,
                                            })
                                        }
                                    >
                                        <Plus data-icon="inline-start" />
                                        {t('Lokasyon ekle')}
                                    </Button>
                                </div>
                            </div>
                        }
                    >
                        <div className="max-h-96 overflow-y-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Lokasyon')}</TableHead>
                                        <TableHead>{t('Koridor')}</TableHead>
                                        <TableHead>{t('Raf')}</TableHead>
                                        <TableHead>{t('Göz')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Ürün')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Adet')}
                                        </TableHead>
                                        <TableHead>{t('Aktif')}</TableHead>
                                        <TableHead className="w-20">
                                            <span className="sr-only">
                                                {t('İşlemler')}
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {locations.map((l) => (
                                        <TableRow
                                            key={l.id}
                                            className={cn(
                                                locationFilter === l.id &&
                                                    'bg-primary/5',
                                            )}
                                        >
                                            <TableCell>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        filterByLocation(
                                                            locationFilter ===
                                                                l.id
                                                                ? null
                                                                : l.id,
                                                        )
                                                    }
                                                    className={cn(
                                                        'font-mono font-medium hover:underline',
                                                        l.is_unassigned &&
                                                            'font-sans text-muted-foreground',
                                                    )}
                                                    title={t(
                                                        'Bu lokasyondaki ürünleri göster',
                                                    )}
                                                >
                                                    {l.code}
                                                </button>
                                            </TableCell>
                                            <TableCell>
                                                {l.aisle ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {l.rack ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {l.bin ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {l.products}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-semibold tabular-nums',
                                                    l.quantity < 0 &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {l.quantity}
                                            </TableCell>
                                            <TableCell>
                                                {l.is_unassigned ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        {t('Sistem')}
                                                    </span>
                                                ) : (
                                                    <StatusSwitch
                                                        status={l.status}
                                                        url={locationStatus.url(
                                                            l.id,
                                                        )}
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {!l.is_unassigned && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            aria-label={t(
                                                                ':code düzenle',
                                                                {
                                                                    code: l.code,
                                                                },
                                                            )}
                                                            onClick={() =>
                                                                setLocationSheet(
                                                                    {
                                                                        mode: 'single',
                                                                        location:
                                                                            l,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    </>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </TableCard>

                    <SectionCard title={t('Depo bilgileri')}>
                        <InfoList
                            items={[
                                { label: t('Kod'), value: warehouse.code },
                                {
                                    label: t('Adres'),
                                    value: warehouse.address ?? '—',
                                },
                                {
                                    label: t('Telefon'),
                                    value: warehouse.phone ?? '—',
                                },
                                {
                                    label: t('Satış'),
                                    value: warehouse.is_sellable
                                        ? t('Satışa açık')
                                        : t('Satışa kapalı'),
                                },
                                {
                                    label: t('Lokasyon'),
                                    value: totals.locations,
                                },
                                {
                                    label: t('Ürün çeşidi'),
                                    value: totals.products,
                                },
                                {
                                    label: t('Toplam adet'),
                                    value: totals.quantity,
                                },
                            ]}
                        />
                        <p className="mt-3 text-xs text-muted-foreground">
                            {t(
                                'Siparişte stok, toplama sırasıyla (koridor → raf → göz, "Atanmamış" en sonda) otomatik düşülür.',
                            )}
                        </p>
                    </SectionCard>
                </div>

                <TableCard
                    toolbar={
                        <Form
                            {...show.form(warehouse.id)}
                            options={{ preserveScroll: true }}
                            className="flex min-w-0 flex-1 flex-wrap items-center gap-3"
                        >
                            <div className="text-sm font-semibold">
                                {t('Depodaki ürünler')}
                            </div>
                            <div className="ml-auto flex w-full flex-wrap gap-2 sm:w-auto">
                                <SearchSelect
                                    name="lokasyon"
                                    value={locationFilter ?? ''}
                                    onChange={(v) =>
                                        filterByLocation(v || null)
                                    }
                                    options={locations.map((l) => ({
                                        value: l.id,
                                        label: l.code,
                                    }))}
                                    placeholder={t('Tüm lokasyonlar')}
                                    clearable
                                    className="w-48"
                                />
                                <div className="relative w-full sm:w-64">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="q"
                                        type="search"
                                        defaultValue={q}
                                        placeholder={t('Ürün adı veya SKU ara')}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                        </Form>
                    }
                    footer={
                        stocks.data.length > 0 ? (
                            <DataPagination
                                paginator={stocks}
                                label={t('ürün')}
                            />
                        ) : undefined
                    }
                >
                    {stocks.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Package />
                                </EmptyMedia>
                                <EmptyTitle>{t('Ürün yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {q || locationFilter
                                        ? t('Filtreye uygun ürün yok.')
                                        : t('Bu depoda stoğu olan ürün yok.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Lokasyonlar')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Miktar')}
                                    </TableHead>
                                    <TableHead className="w-24">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stocks.data.map((s) => (
                                    <TableRow key={s.product_id}>
                                        <TableCell className="max-w-md whitespace-normal">
                                            <div className="flex items-center gap-3">
                                                {s.image ? (
                                                    <img
                                                        src={s.image}
                                                        alt=""
                                                        className="size-10 shrink-0 rounded-md border object-contain"
                                                    />
                                                ) : (
                                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                                                        <Package className="size-4" />
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <Link
                                                        href={productEdit(
                                                            s.product_id,
                                                        )}
                                                        className="line-clamp-1 font-medium hover:underline"
                                                    >
                                                        {s.name ?? '—'}
                                                    </Link>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {s.sku}
                                                        {s.category
                                                            ? ` · ${s.category}`
                                                            : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <div className="flex flex-wrap gap-1">
                                                {s.locations
                                                    .filter(
                                                        (l) =>
                                                            l.warehouse_id ===
                                                            warehouse.id,
                                                    )
                                                    .map((l) => (
                                                        <Badge
                                                            key={`${l.location_id}-${l.lot_id ?? ''}`}
                                                            variant="outline"
                                                            className={cn(
                                                                'font-mono text-[11px]',
                                                                (l.quantity <
                                                                    0 ||
                                                                    l.expired) &&
                                                                    'border-destructive text-destructive',
                                                            )}
                                                            title={
                                                                l.expires_at
                                                                    ? t(
                                                                          'SKT :expires_at',
                                                                          {
                                                                              expires_at:
                                                                                  l.expires_at,
                                                                          },
                                                                      )
                                                                    : undefined
                                                            }
                                                        >
                                                            {l.code}
                                                            {l.lot
                                                                ? ` · ${l.lot}`
                                                                : ''}
                                                            : {l.quantity}
                                                        </Badge>
                                                    ))}
                                            </div>
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right font-semibold tabular-nums',
                                                s.quantity < 0 &&
                                                    'text-destructive',
                                            )}
                                        >
                                            {s.quantity}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':sku stok işlemi',
                                                    { sku: s.sku },
                                                )}
                                                onClick={() =>
                                                    setOperation((o) => ({
                                                        row: s,
                                                        key: o.key + 1,
                                                    }))
                                                }
                                            >
                                                <ArrowLeftRight />
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon-sm"
                                            >
                                                <Link
                                                    href={stockMovements({
                                                        query: {
                                                            warehouse:
                                                                warehouse.id,
                                                            product:
                                                                s.product_id,
                                                        },
                                                    })}
                                                    aria-label={t(
                                                        ':sku hareketleri',
                                                        { sku: s.sku },
                                                    )}
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

                <TableCard
                    toolbar={
                        <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
                            <div className="text-sm font-semibold">
                                {t('Stok hareketleri')}
                                <span className="ml-2 font-normal text-muted-foreground">
                                    {totals.movements}
                                </span>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={stockMovements({
                                        query: { warehouse: warehouse.id },
                                    })}
                                >
                                    <Boxes data-icon="inline-start" />
                                    {t('Filtreli hareket ekranı')}
                                </Link>
                            </Button>
                        </div>
                    }
                    footer={
                        movements.data.length > 0 ? (
                            <DataPagination
                                paginator={movements}
                                label={t('hareket')}
                            />
                        ) : undefined
                    }
                >
                    {movements.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <History />
                                </EmptyMedia>
                                <EmptyTitle>{t('Hareket yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('Bu depoda henüz stok hareketi olmadı.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Tarih')}</TableHead>
                                    <TableHead>{t('Ürün')}</TableHead>
                                    <TableHead>{t('Lokasyon')}</TableHead>
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
                                {movements.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDateTime(m.created_at)}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            <div className="line-clamp-1 font-medium whitespace-normal">
                                                {m.product_name ?? '—'}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {m.product_sku}
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {m.location ?? '—'}
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

            <LocationSheet
                state={locationSheet}
                warehouseId={warehouse.id}
                onClose={() => setLocationSheet({ mode: 'closed' })}
            />

            <Sheet
                open={operation.row !== null}
                onOpenChange={(v) =>
                    v ? null : setOperation((o) => ({ ...o, row: null }))
                }
            >
                <SheetContent className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{t('Stok işlemi')}</SheetTitle>
                        <SheetDescription>
                            {operation.row
                                ? `${operation.row.sku} — ${operation.row.name}`
                                : null}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4">
                        {operation.row ? (
                            <StockOperationForm
                                key={operation.key}
                                row={{
                                    id: operation.row.product_id,
                                    quantities: operation.row.quantities,
                                    locations: operation.row.locations,
                                    tracking: operation.row.tracking,
                                    expiry_tracking:
                                        operation.row.expiry_tracking,
                                }}
                                warehouses={allWarehouses}
                                locations={allLocations}
                                defaultWarehouseId={warehouse.id}
                                defaultOperation="relocate"
                                onDone={() =>
                                    setOperation((o) => ({ ...o, row: null }))
                                }
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

WarehouseShow.layout = {
    breadcrumbs: [{ title: t('Depolar'), href: index() }],
};
