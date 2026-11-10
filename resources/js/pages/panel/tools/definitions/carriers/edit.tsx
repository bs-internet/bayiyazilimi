import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Plus, Save, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

import { ActiveBadge } from '@/components/admin/active-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import {
    CheckboxField,
    SelectField,
    TextField,
} from '@/components/shop/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    add,
    compare,
    sub,
    times,
    toFixed,
    toNumber,
    withRate,
} from '@/lib/decimal';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import {
    destroy,
    index as carriersIndex,
    template,
    update,
} from '@/routes/panel/shop/carriers';
import {
    importMethod as importRates,
    update as updateRates,
} from '@/routes/panel/shop/carriers/rates';
import type { CarrierRateRow, ShopCarrier, ShopOption } from '@/types';

type Props = {
    carrier: ShopCarrier;
};

type Row = { min_desi: string; max_desi: string; price: string };

const STATUS_OPTIONS: ShopOption[] = [
    { value: 'active', label: t('Aktif') },
    { value: 'passive', label: t('Pasif') },
];

const toRow = (r: CarrierRateRow): Row => ({
    min_desi: r.min_desi === null ? '' : String(r.min_desi),
    max_desi: String(r.max_desi),
    price: String(r.price),
});

const tl = (v: string) => `${formatNumber(v, 2, 2)} TL`;

/** Sunucudaki ShippingService::priceFor ile aynı mantık (önizleme). */
function previewPrice(
    rows: Row[],
    desi: string,
    extra: string,
    includesTax: boolean,
): string | null {
    // Kesin ondalık (@/lib/decimal): sunucuyla aynı sonuç.
    const sorted = rows
        .filter((r) => r.max_desi !== '' && r.price !== '')
        .map((r) => ({
            min: r.min_desi === '' ? '0' : r.min_desi,
            max: r.max_desi,
            price: r.price,
        }))
        .sort((a, b) => compare(a.max, b.max));

    if (sorted.length === 0) {
        return null;
    }

    const match =
        sorted.find(
            (r) => compare(desi, r.min) >= 0 && compare(desi, r.max) <= 0,
        ) ?? sorted.find((r) => compare(desi, r.max) <= 0);
    let price: string;

    if (match) {
        price = toFixed(match.price);
    } else {
        const last = sorted[sorted.length - 1];
        // Tablonun üstündeki her (başlamış) desi için ek ücret.
        const steps = Math.ceil(toNumber(sub(desi, last.max)));
        price = add(last.price, times(extra, steps));
    }

    return includesTax ? price : withRate(price, 20);
}

/** Excel yükleme: dosya seçilince doğrudan gönderilir (iç içe form yok). */
function ImportButton({ carrierId }: { carrierId: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    return (
        <>
            <input
                ref={input}
                type="file"
                accept=".xlsx"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (!file) {
                        return;
                    }

                    setUploading(true);
                    router.post(
                        importRates.url(carrierId),
                        { file },
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onFinish: () => {
                                setUploading(false);

                                if (input.current) {
                                    input.current.value = '';
                                }
                            },
                        },
                    );
                }}
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={uploading}
                onClick={() => input.current?.click()}
            >
                <Upload data-icon="inline-start" />
                {uploading ? t('Yükleniyor…') : t("Excel'den yükle")}
            </Button>
        </>
    );
}

export default function CarrierEdit({ carrier }: Props) {
    const { errors: pageErrors } = usePage().props as {
        errors: Record<string, string>;
    };
    const serverRates = JSON.stringify(carrier.rates ?? []);
    const [ratesSource, setRatesSource] = useState(serverRates);
    const [rows, setRows] = useState<Row[]>((carrier.rates ?? []).map(toRow));
    const [rateErrors, setRateErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [testDesi, setTestDesi] = useState('5');

    // Sunucudaki tablo değişince (kaydet / Excel) satırları yeniden kur.
    if (ratesSource !== serverRates) {
        setRatesSource(serverRates);
        setRows((carrier.rates ?? []).map(toRow));
    }

    const setRow = (i: number, patch: Partial<Row>) =>
        setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...patch } : r)));

    const addRow = () => {
        const lastMax = rows.length > 0 ? rows[rows.length - 1].max_desi : '0';
        setRows([...rows, { min_desi: lastMax, max_desi: '', price: '' }]);
    };

    const saveRates = () => {
        setSaving(true);
        router.put(
            updateRates.url(carrier.id),
            { rates: rows },
            {
                preserveScroll: true,
                onError: (e) => setRateErrors(e),
                onSuccess: () => setRateErrors({}),
                onFinish: () => setSaving(false),
            },
        );
    };

    const preview = previewPrice(
        rows,
        testDesi || '0',
        carrier.extra_desi_price,
        carrier.prices_include_tax,
    );

    return (
        <>
            <Head title={carrier.name} />

            <Form
                {...update.form(carrier.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing, errors }) => (
                    <Page embedded>
                        <PageTitle
                            compact
                            title={carrier.name}
                            badges={
                                <>
                                    <ActiveBadge
                                        active={carrier.status === 'active'}
                                    />
                                    {carrier.is_default && (
                                        <Badge variant="secondary">
                                            {t('Varsayılan')}
                                        </Badge>
                                    )}
                                </>
                            }
                            meta={
                                <>
                                    <span>
                                        {t('Kod: :code', {
                                            code: carrier.code,
                                        })}
                                    </span>
                                    <MetaDot />
                                    <span>
                                        {carrier.rates?.length ?? 0}{' '}
                                        {t('desi satırı')}
                                    </span>
                                    {carrier.delivery_days ? (
                                        <>
                                            <MetaDot />
                                            <span>
                                                {t(
                                                    ':delivery_days günde teslim',
                                                    {
                                                        delivery_days:
                                                            carrier.delivery_days,
                                                    },
                                                )}
                                            </span>
                                        </>
                                    ) : null}
                                </>
                            }
                            actions={
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={carriersIndex()}>
                                            <ArrowLeft data-icon="inline-start" />
                                            {t('Kargo firmaları')}
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save data-icon="inline-start" />
                                        {t('Kaydet')}
                                    </Button>
                                </>
                            }
                        />

                        <EntityLayout stackUntil="2xl">
                            <EntityMain>
                                <SectionCard
                                    flush
                                    title={t('Desi fiyat tablosu')}
                                    description={t(
                                        'Her satır bir desi aralığı ve o aralığın ücretidir (TL). Bitiş desisi dahildir.',
                                    )}
                                    action={
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={template.url({
                                                        query: {
                                                            carrier: carrier.id,
                                                        },
                                                    })}
                                                >
                                                    <Download data-icon="inline-start" />
                                                    {t('Şablon')}
                                                </a>
                                            </Button>
                                            <ImportButton
                                                carrierId={carrier.id}
                                            />
                                        </div>
                                    }
                                    footer={
                                        <div className="flex w-full flex-wrap items-center justify-between gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addRow}
                                            >
                                                <Plus data-icon="inline-start" />
                                                {t('Satır ekle')}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={saveRates}
                                                disabled={saving}
                                            >
                                                <Save data-icon="inline-start" />
                                                {t('Tabloyu kaydet')}
                                            </Button>
                                        </div>
                                    }
                                >
                                    <div className="[&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        {t('Başlangıç desi')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('Bitiş desi')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('Fiyat (TL)')}
                                                    </TableHead>
                                                    <TableHead className="w-10" />
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {rows.length === 0 ? (
                                                    <TableRow>
                                                        <TableCell
                                                            colSpan={4}
                                                            className="py-8 text-center text-sm text-muted-foreground"
                                                        >
                                                            {t(
                                                                "Tablo boş. Satır ekleyin veya Excel'den yükleyin.",
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ) : null}
                                                {rows.map((row, i) => (
                                                    <TableRow key={i}>
                                                        {(
                                                            [
                                                                'min_desi',
                                                                'max_desi',
                                                                'price',
                                                            ] as const
                                                        ).map((key) => (
                                                            <TableCell
                                                                key={key}
                                                            >
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min={0}
                                                                    value={
                                                                        row[key]
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setRow(
                                                                            i,
                                                                            {
                                                                                [key]: e
                                                                                    .target
                                                                                    .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    onKeyDown={(
                                                                        e,
                                                                    ) => {
                                                                        if (
                                                                            e.key ===
                                                                            'Enter'
                                                                        ) {
                                                                            e.preventDefault();
                                                                        }
                                                                    }}
                                                                    aria-invalid={Boolean(
                                                                        rateErrors[
                                                                            `rates.${i}.${key}`
                                                                        ],
                                                                    )}
                                                                    className="h-8 min-w-24 tabular-nums"
                                                                />
                                                            </TableCell>
                                                        ))}
                                                        <TableCell>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-sm"
                                                                aria-label={t(
                                                                    'Satırı sil',
                                                                )}
                                                                onClick={() =>
                                                                    setRows(
                                                                        rows.filter(
                                                                            (
                                                                                _,
                                                                                j,
                                                                            ) =>
                                                                                j !==
                                                                                i,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                    {Object.keys(rateErrors).length > 0 ||
                                    pageErrors.file ? (
                                        <div className="flex flex-col gap-1 border-t px-5 py-3">
                                            {Object.keys(rateErrors).length >
                                            0 ? (
                                                <InputError
                                                    message={t(
                                                        'Tablodaki hatalı satırları düzeltin (bitiş desi ve fiyat zorunlu).',
                                                    )}
                                                />
                                            ) : null}
                                            <InputError
                                                message={pageErrors.file}
                                            />
                                        </div>
                                    ) : null}
                                    <p className="border-t px-5 py-3 text-xs text-muted-foreground">
                                        {t(
                                            'Excel: ilk sayfa okunur, ilk satır başlıktır. Sütunlar',
                                        )}{' '}
                                        <strong>
                                            {t(
                                                'Desi Başlangıç | Desi Bitiş | Fiyat',
                                            )}
                                        </strong>{' '}
                                        {t('ya da')}{' '}
                                        <strong>{t('Desi | Fiyat')}</strong>{' '}
                                        {t(
                                            '(her satır bir üst sınır). Yükleme mevcut tabloyu tamamen değiştirir; "1.250,50" gibi Türkçe sayılar desteklenir.',
                                        )}
                                    </p>
                                </SectionCard>

                                <SectionCard
                                    title={t('Ek desi ücreti ve vergi')}
                                    description={t(
                                        'Tablonun en üst desisini aşan gönderiler ve KDV hesabı.',
                                    )}
                                >
                                    <FieldGroup>
                                        <TextField
                                            name="extra_desi_price"
                                            label={t('Ek desi ücreti (TL)')}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            defaultValue={
                                                carrier.extra_desi_price
                                            }
                                            description={t(
                                                'Tablonun en üst desisini aşan her desi için eklenir.',
                                            )}
                                            error={errors.extra_desi_price}
                                        />
                                        <CheckboxField
                                            name="prices_include_tax"
                                            label={t('Fiyatlar KDV dahil')}
                                            description={t(
                                                'İşaretli değilse müşteriye %20 KDV eklenerek yansıtılır.',
                                            )}
                                            defaultChecked={
                                                carrier.prices_include_tax
                                            }
                                        />
                                    </FieldGroup>
                                </SectionCard>
                            </EntityMain>

                            <EntitySidebar>
                                <SectionCard title={t('Durum')}>
                                    <FieldGroup>
                                        <SelectField
                                            name="status"
                                            label={t('Durum')}
                                            options={STATUS_OPTIONS}
                                            required
                                            defaultValue={carrier.status}
                                            error={errors.status}
                                        />
                                        <CheckboxField
                                            name="is_default"
                                            label={t('Varsayılan firma')}
                                            description={t(
                                                'Sepette ilk seçili gelen firma.',
                                            )}
                                            defaultChecked={carrier.is_default}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                <SectionCard title={t('Firma bilgileri')}>
                                    <FieldGroup>
                                        <div className="grid grid-cols-2 gap-3">
                                            <TextField
                                                name="code"
                                                label={t('Kod')}
                                                required
                                                defaultValue={carrier.code}
                                                error={errors.code}
                                            />
                                            <TextField
                                                name="sort_order"
                                                label={t('Sıra')}
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    carrier.sort_order
                                                }
                                                error={errors.sort_order}
                                            />
                                        </div>
                                        <TextField
                                            name="name"
                                            label={t('Firma adı')}
                                            required
                                            defaultValue={carrier.name}
                                            error={errors.name}
                                        />
                                        <Field
                                            data-invalid={Boolean(errors.logo)}
                                        >
                                            <FieldLabel htmlFor="logo">
                                                {t('Logo')}
                                            </FieldLabel>
                                            {carrier.logo_url ? (
                                                <img
                                                    src={carrier.logo_url}
                                                    alt=""
                                                    className="h-12 w-fit rounded-md border bg-white object-contain p-1"
                                                />
                                            ) : null}
                                            <Input
                                                id="logo"
                                                name="logo"
                                                type="file"
                                                accept="image/*"
                                            />
                                            <InputError message={errors.logo} />
                                        </Field>
                                        {carrier.logo_url ? (
                                            <CheckboxField
                                                name="remove_logo"
                                                label={t('Logoyu kaldır')}
                                            />
                                        ) : null}
                                        <TextField
                                            name="tracking_url"
                                            label={t('Takip bağlantısı')}
                                            defaultValue={carrier.tracking_url}
                                            placeholder="https://.../takip?no={code}"
                                            description={t(
                                                '{code} yerine siparişin takip numarası yazılır.',
                                            )}
                                            error={errors.tracking_url}
                                        />
                                        <TextField
                                            name="delivery_days"
                                            label={t('Teslim süresi (gün)')}
                                            type="number"
                                            min={0}
                                            defaultValue={carrier.delivery_days}
                                            error={errors.delivery_days}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                <SectionCard
                                    title={t('Ücret önizleme')}
                                    description={t(
                                        'Kaydedilmemiş satırlarla hesaplanır.',
                                    )}
                                >
                                    <div className="flex flex-col gap-3">
                                        <Field>
                                            <FieldLabel htmlFor="preview-desi">
                                                {t('Desi')}
                                            </FieldLabel>
                                            <Input
                                                id="preview-desi"
                                                type="number"
                                                step="0.1"
                                                min={0}
                                                value={testDesi}
                                                onChange={(e) =>
                                                    setTestDesi(e.target.value)
                                                }
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                    }
                                                }}
                                            />
                                        </Field>
                                        <div className="flex items-center justify-between rounded-md bg-muted/60 px-3 py-2.5">
                                            <span className="text-sm text-muted-foreground">
                                                {t('Müşteriye yansıyan')}
                                            </span>
                                            <span className="text-lg font-semibold tabular-nums">
                                                {preview === null
                                                    ? '—'
                                                    : tl(preview)}
                                            </span>
                                        </div>
                                    </div>
                                </SectionCard>
                            </EntitySidebar>
                        </EntityLayout>

                        <FormActions
                            destroy={
                                <DeleteButton
                                    action={destroy(carrier.id)}
                                    title={t(':carrier_name silinsin mi?', {
                                        carrier_name: carrier.name,
                                    })}
                                    description={t(
                                        'Siparişlerde kullanılmış firma silinemez; pasifleştirebilirsiniz.',
                                    )}
                                />
                            }
                        >
                            <Button type="submit" disabled={processing}>
                                <Save data-icon="inline-start" />
                                {t('Kaydet')}
                            </Button>
                        </FormActions>
                    </Page>
                )}
            </Form>
        </>
    );
}

CarrierEdit.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Kargo Firmaları'), href: carriersIndex() },
    ],
};
