import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    Coins,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    Page,
    PageTitle,
    SectionCard,
    TableCard,
} from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import {
    SelectField,
    StatusSwitch,
    SwitchField,
} from '@/components/shop/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
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
import {
    index,
    settings as settingsRoute,
    store,
    sync,
    update,
} from '@/routes/panel/shop/exchange-rates';
import {
    deleted as currencyDeleted,
    destroy as currencyDestroy,
    status as currencyStatus,
    store as currencyStore,
    update as currencyUpdate,
} from '@/routes/panel/tools/definitions/currencies';
import type { ShopOption, Status } from '@/types';

type CurrencyRow = {
    id: string;
    code: string;
    name: string;
    symbol: string;
    decimal_places: number;
    thousands_separator: string;
    decimal_separator: string;
    sort_order: number;
    status: Status;
    exchange_rate: string;
    rate_source: string;
    rate_updated_at: string | null;
    is_default: boolean;
};

type AvailableCurrency = {
    code: string;
    name: string;
    unit: number;
    restorable: boolean;
};

type Props = {
    currencies: CurrencyRow[];
    sources: ShopOption[];
    rateType: string;
    rateTypes: ShopOption[];
    autoUpdate: boolean;
    available?: AvailableCurrency[];
};

const SEPARATOR_OPTIONS = [
    { value: ',', label: t('Virgül (,)') },
    { value: '.', label: t('Nokta (.)') },
    { value: ' ', label: t('Boşluk ( )') },
];

function TextField({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    required = true,
}: {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    error?: string;
    type?: string;
    required?: boolean;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                name={name}
                type={type}
                min={type === 'number' ? 0 : undefined}
                defaultValue={defaultValue ?? ''}
                required={required}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    );
}

function SeparatorField({
    name,
    label,
    defaultValue,
    error,
}: {
    name: 'thousands_separator' | 'decimal_separator';
    label: string;
    defaultValue: string;
    error?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <NativeSelect
                id={name}
                name={name}
                defaultValue={defaultValue}
                required
                aria-invalid={Boolean(error)}
            >
                {SEPARATOR_OPTIONS.map((o) => (
                    <NativeSelectOption key={o.value} value={o.value}>
                        {o.label}
                    </NativeSelectOption>
                ))}
            </NativeSelect>
            <InputError message={error} />
        </Field>
    );
}

/**
 * Para birimi tanımı (ad, sembol, biçim) ekleme / düzenleme paneli.
 * `currency` yoksa elle yeni para birimi ekler (pasif, kaynağı "Elle").
 */
function CurrencySheet({
    open,
    onOpenChange,
    currency,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    currency: CurrencyRow | null;
}) {
    const form = currency
        ? currencyUpdate.form(currency.id)
        : currencyStore.form();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {currency
                            ? t(':code düzenle', { code: currency.code })
                            : t('Para birimini elle ekle')}
                    </SheetTitle>
                    <SheetDescription>
                        {currency
                            ? t('Ad, sembol ve fiyat biçimi.')
                            : t(
                                  'TCMB listesinde olmayan para birimleri için. Kur elle girilir; para birimi pasif eklenir.',
                              )}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    key={currency?.id ?? 'new'}
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <FieldGroup>
                                {!currency && (
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="passive"
                                    />
                                )}
                                <div className="grid grid-cols-2 gap-3">
                                    <TextField
                                        name="code"
                                        label={t('Kod')}
                                        defaultValue={currency?.code}
                                        error={errors.code}
                                    />
                                    <TextField
                                        name="sort_order"
                                        label={t('Sıra No')}
                                        type="number"
                                        required={false}
                                        defaultValue={currency?.sort_order}
                                        error={errors.sort_order}
                                    />
                                </div>
                                <TextField
                                    name="name"
                                    label={t('Ad')}
                                    defaultValue={currency?.name}
                                    error={errors.name}
                                />
                                <div className="grid grid-cols-2 gap-3">
                                    <TextField
                                        name="symbol"
                                        label={t('Sembol')}
                                        defaultValue={currency?.symbol}
                                        error={errors.symbol}
                                    />
                                    <TextField
                                        name="decimal_places"
                                        label={t('Basamak sayısı')}
                                        type="number"
                                        defaultValue={
                                            currency?.decimal_places ?? 2
                                        }
                                        error={errors.decimal_places}
                                    />
                                    <SeparatorField
                                        name="thousands_separator"
                                        label={t('Binlik ayracı')}
                                        defaultValue={
                                            currency?.thousands_separator ?? '.'
                                        }
                                        error={errors.thousands_separator}
                                    />
                                    <SeparatorField
                                        name="decimal_separator"
                                        label={t('Onluk ayracı')}
                                        defaultValue={
                                            currency?.decimal_separator ?? ','
                                        }
                                        error={errors.decimal_separator}
                                    />
                                </div>
                            </FieldGroup>
                            <FormActions
                                destroy={
                                    currency && !currency.is_default ? (
                                        <DeleteButton
                                            action={currencyDestroy(
                                                currency.id,
                                            )}
                                            title={t(
                                                ':code listeden kaldırılsın mı?',
                                                { code: currency.code },
                                            )}
                                            description={t(
                                                "Para birimi silinir ('Silinenler'den ya da TCMB listesinden yeniden ekleyerek geri alınabilir).",
                                            )}
                                        />
                                    ) : null
                                }
                            >
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {t('Kaydet')}
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

/** TCMB'nin yayımladığı para birimlerinden seçip ekleme penceresi. */
function AddCurrencyDialog({
    open,
    onOpenChange,
    available,
    onManual,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onManual: () => void;
    available?: AvailableCurrency[];
}) {
    const [query, setQuery] = useState('');
    const [adding, setAdding] = useState<string | null>(null);

    const filtered = useMemo(() => {
        const q = query.trim().toLocaleLowerCase('tr-TR');

        return (available ?? []).filter(
            (c) =>
                q === '' ||
                c.code.toLocaleLowerCase('tr-TR').includes(q) ||
                c.name.toLocaleLowerCase('tr-TR').includes(q),
        );
    }, [available, query]);

    const add = (code: string) => {
        setAdding(code);
        router.post(
            store.url(),
            { code },
            {
                preserveScroll: true,
                // Yeni satır ve kurlar defaultValue ile dolduğu için sayfa yeniden kurulur.
                preserveState: false,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setAdding(null),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Para birimi ekle')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            "TCMB'nin kur yayımladığı para birimleri. Eklenen para birimi TCMB kaynaklı olur ve kuru hemen çekilir.",
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Kod veya ad ara (USD, Euro…)')}
                        className="pl-10"
                        autoFocus
                    />
                </div>

                <div className="-mx-6 max-h-80 overflow-y-auto border-y">
                    {available === undefined ? (
                        <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                            <Spinner />
                            {t('TCMB listesi alınıyor…')}
                        </div>
                    ) : filtered.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            {available.length === 0
                                ? t(
                                      'TCMB listesindeki tüm para birimleri zaten ekli.',
                                  )
                                : t('Aramaya uygun para birimi yok.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {filtered.map((c) => (
                                <li
                                    key={c.code}
                                    className="flex items-center gap-3 px-6 py-2.5"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted font-mono text-xs font-semibold">
                                        {c.code}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium">
                                            {c.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {c.unit > 1
                                                ? t(
                                                      'TCMB :unit birim üzerinden yayımlar',
                                                      { unit: c.unit },
                                                  )
                                                : 'TCMB'}
                                            {c.restorable &&
                                                t(' · daha önce silinmiş')}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant={
                                            c.restorable ? 'outline' : 'default'
                                        }
                                        disabled={adding !== null}
                                        onClick={() => add(c.code)}
                                    >
                                        {adding === c.code ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : c.restorable ? (
                                            <RotateCcw data-icon="inline-start" />
                                        ) : (
                                            <Plus data-icon="inline-start" />
                                        )}
                                        {c.restorable
                                            ? t('Geri al')
                                            : t('Ekle')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <p className="text-sm text-muted-foreground">
                    {t('Listede yok mu?')}{' '}
                    <button
                        type="button"
                        onClick={onManual}
                        className="cursor-pointer font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        {t('Elle ekleyin')}
                    </button>
                    .
                </p>
            </DialogContent>
        </Dialog>
    );
}

export default function ExchangeRateIndex({
    currencies,
    sources,
    rateType,
    rateTypes,
    autoUpdate,
    available,
}: Props) {
    const [syncing, setSyncing] = useState(false);
    const [adding, setAdding] = useState(false);
    const [sheet, setSheet] = useState<{
        open: boolean;
        currency: CurrencyRow | null;
    }>({ open: false, currency: null });
    const tcmbCount = currencies.filter(
        (c) => !c.is_default && c.rate_source === 'tcmb',
    ).length;

    const openAdd = () => {
        setAdding(true);
        router.reload({ only: ['available'] });
    };

    return (
        <>
            <Head title={t('Para Birimleri')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Para Birimleri')}
                    meta={t(
                        ':currencies_length para birimi · :tcmb_count TCMB kaynaklı',
                        {
                            currencies_length: currencies.length,
                            tcmb_count: tcmbCount,
                        },
                    )}
                    description={t(
                        'Para birimi tanımları ve TL karşısındaki kurları.',
                    )}
                    actions={
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={currencyDeleted()}>
                                    <Archive data-icon="inline-start" />
                                    {t('Silinenler')}
                                </Link>
                            </Button>
                            <Button variant="outline" onClick={openAdd}>
                                <Plus data-icon="inline-start" />
                                {t('Para birimi ekle')}
                            </Button>
                            <Button
                                disabled={syncing}
                                onClick={() => {
                                    setSyncing(true);
                                    router.post(
                                        sync().url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            // Inertia v3'te post varsayılanı preserveState: true;
                                            // kur alanları defaultValue ile dolduğu için yeni
                                            // kurların görünmesi (ve sonraki "Kaydet"in eski
                                            // değeri geri yazmaması) için sayfa yeniden kurulur.
                                            preserveState: false,
                                            onFinish: () => setSyncing(false),
                                        },
                                    );
                                }}
                            >
                                <RefreshCw
                                    data-icon="inline-start"
                                    className={syncing ? 'animate-spin' : ''}
                                />
                                {t("TCMB'den Güncelle")}
                            </Button>
                        </>
                    }
                />

                <div className="flex min-w-0 flex-col gap-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <SectionCard title={t('Nasıl çalışır?')}>
                            <div className="flex flex-col gap-3 text-sm text-muted-foreground">
                                <p>
                                    {t(
                                        '"Aktif" anahtarı açık olan para birimleri fiyatlarda ve tekliflerde seçilebilir; varsayılan para birimi (TL) her zaman 1\'dir.',
                                    )}
                                </p>
                                <p>
                                    {t(
                                        'Kaynağı "TCMB" olan kurlar TCMB kuruyla güncellenir; "Elle" olanlar yalnızca burada girdiğiniz değerle kalır.',
                                    )}
                                </p>
                            </div>
                        </SectionCard>
                        <SectionCard title={t('TCMB ayarları')}>
                            <Form
                                {...settingsRoute.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <FieldGroup>
                                        <SelectField
                                            name="tcmb_rate_type"
                                            label={t('TCMB kur tipi')}
                                            options={rateTypes}
                                            required
                                            defaultValue={rateType}
                                            error={errors.tcmb_rate_type}
                                        />
                                        <SwitchField
                                            name="tcmb_auto_update"
                                            label={t(
                                                'Kurları otomatik güncelle',
                                            )}
                                            description={t(
                                                'Hafta içi 09:05 ve 15:45 (zamanlayıcı/cron gerekir).',
                                            )}
                                            defaultChecked={autoUpdate}
                                        />
                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : (
                                                    <Save data-icon="inline-start" />
                                                )}
                                                {t('Kaydet')}
                                            </Button>
                                        </div>
                                    </FieldGroup>
                                )}
                            </Form>
                        </SectionCard>
                    </div>

                    <Form {...update.form()} options={{ preserveScroll: true }}>
                        {({ processing, errors }) => (
                            <TableCard
                                footer={
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'Kurlar TL bazlıdır: 1 birim = X TL.',
                                            )}
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Save data-icon="inline-start" />
                                            {t('Kaydet')}
                                        </Button>
                                    </div>
                                }
                            >
                                {currencies.length === 0 ? (
                                    <Empty className="py-12">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Coins />
                                            </EmptyMedia>
                                            <EmptyTitle>
                                                {t('Para birimi yok')}
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                {t(
                                                    'TCMB listesinden para birimi ekleyin.',
                                                )}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t('Para birimi')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Aktif')}
                                                </TableHead>
                                                <TableHead className="w-44">
                                                    {t('Kur (TL)')}
                                                </TableHead>
                                                <TableHead className="w-36">
                                                    {t('Kaynak')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Son güncelleme')}
                                                </TableHead>
                                                <TableHead className="w-12">
                                                    <span className="sr-only">
                                                        {t('İşlemler')}
                                                    </span>
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {currencies.map((c) => (
                                                <TableRow
                                                    key={`${c.id}-${c.exchange_rate}-${c.rate_source}`}
                                                >
                                                    <TableCell>
                                                        <div className="flex items-center gap-3">
                                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-sm font-semibold">
                                                                {c.symbol}
                                                            </span>
                                                            <div>
                                                                <div className="flex items-center gap-2 font-medium">
                                                                    {c.code}
                                                                    {c.is_default && (
                                                                        <Badge variant="secondary">
                                                                            {t(
                                                                                'Varsayılan',
                                                                            )}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {c.name}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {c.is_default ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                {t('Her zaman')}
                                                            </span>
                                                        ) : (
                                                            <StatusSwitch
                                                                status={
                                                                    c.status
                                                                }
                                                                url={
                                                                    currencyStatus(
                                                                        c.id,
                                                                    ).url
                                                                }
                                                                label={t(
                                                                    'Kullanımda',
                                                                )}
                                                            />
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {c.is_default ? (
                                                            <span className="text-sm tabular-nums">
                                                                1
                                                            </span>
                                                        ) : (
                                                            <Input
                                                                name={`rates[${c.id}][exchange_rate]`}
                                                                type="number"
                                                                step="0.000001"
                                                                min={0}
                                                                defaultValue={Number(
                                                                    c.exchange_rate,
                                                                )}
                                                                aria-label={t(
                                                                    ':code kuru',
                                                                    {
                                                                        code: c.code,
                                                                    },
                                                                )}
                                                                aria-invalid={Boolean(
                                                                    errors[
                                                                        `rates.${c.id}.exchange_rate`
                                                                    ],
                                                                )}
                                                                className="h-8 tabular-nums"
                                                            />
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {c.is_default ? (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name={`rates[${c.id}][rate_source]`}
                                                                    value="manual"
                                                                />
                                                                <span className="text-sm text-muted-foreground">
                                                                    —
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <NativeSelect
                                                                size="sm"
                                                                name={`rates[${c.id}][rate_source]`}
                                                                defaultValue={
                                                                    c.rate_source
                                                                }
                                                                aria-label={t(
                                                                    ':code kur kaynağı',
                                                                    {
                                                                        code: c.code,
                                                                    },
                                                                )}
                                                            >
                                                                {sources.map(
                                                                    (s) => (
                                                                        <NativeSelectOption
                                                                            key={
                                                                                s.value
                                                                            }
                                                                            value={
                                                                                s.value
                                                                            }
                                                                        >
                                                                            {
                                                                                s.label
                                                                            }
                                                                        </NativeSelectOption>
                                                                    ),
                                                                )}
                                                            </NativeSelect>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                                        {formatDateTime(
                                                            c.rate_updated_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right whitespace-nowrap">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            aria-label={t(
                                                                ':code düzenle',
                                                                {
                                                                    code: c.code,
                                                                },
                                                            )}
                                                            onClick={() =>
                                                                setSheet({
                                                                    open: true,
                                                                    currency: c,
                                                                })
                                                            }
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </TableCard>
                        )}
                    </Form>
                </div>
            </Page>

            <AddCurrencyDialog
                open={adding}
                onOpenChange={setAdding}
                available={available}
                onManual={() => {
                    setAdding(false);
                    setSheet({ open: true, currency: null });
                }}
            />

            <CurrencySheet
                open={sheet.open}
                onOpenChange={(open) => setSheet((s) => ({ ...s, open }))}
                currency={sheet.currency}
            />
        </>
    );
}

ExchangeRateIndex.layout = {
    breadcrumbs: [{ title: t('Para Birimleri'), href: index() }],
};
