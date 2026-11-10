import { Truck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { SectionCard, TotalsList } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { isPositive } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { districts as districtsRoute } from '@/routes/locations';
import type { CheckoutKit, LocationSelectsProps } from './kit';

type Option = { id: string; name: string };

function fieldId(name?: string): string | undefined {
    return name ? `co-${name.replace(/[[\]]+/g, '-')}` : undefined;
}

/** İl → ilçe (adla gönderilir; ilçeler il seçilince yüklenir). */
function AppLocationSelects({
    prefix,
    cities,
    defaultCity,
    defaultDistrict,
    cityError,
    districtError,
}: LocationSelectsProps) {
    const [city, setCity] = useState(defaultCity ?? '');
    const [district, setDistrict] = useState(defaultDistrict ?? '');
    const [options, setOptions] = useState<Option[]>([]);
    const [loading, setLoading] = useState(false);
    const cityId = cities.find((c) => c.name === city)?.id ?? null;

    useEffect(() => {
        if (!cityId) {
            return;
        }

        const controller = new AbortController();

        fetch(districtsRoute(cityId).url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data: Option[]) => setOptions(data))
            .catch(() => undefined)
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [cityId]);

    const visible = cityId ? options : [];

    return (
        <>
            <Field data-invalid={Boolean(cityError)}>
                <FieldLabel htmlFor={`${prefix}-city`}>{t('İl')}</FieldLabel>
                <SearchSelect
                    id={`${prefix}-city`}
                    name={`${prefix}[city]`}
                    value={city}
                    required
                    aria-invalid={Boolean(cityError)}
                    placeholder={t('İl seçin')}
                    searchPlaceholder={t('İl ara…')}
                    options={cities.map((c) => ({
                        value: c.name,
                        label: c.name,
                    }))}
                    onChange={(next) => {
                        setCity(next);
                        setDistrict('');
                        setOptions([]);
                        setLoading(true);
                    }}
                />
                <InputError message={cityError} />
            </Field>
            <Field data-invalid={Boolean(districtError)}>
                <FieldLabel htmlFor={`${prefix}-district`}>
                    {t('İlçe')}
                </FieldLabel>
                <SearchSelect
                    id={`${prefix}-district`}
                    name={`${prefix}[district]`}
                    value={district}
                    required
                    disabled={!cityId}
                    aria-invalid={Boolean(districtError)}
                    placeholder={loading ? t('Yükleniyor…') : t('İlçe seçin')}
                    searchPlaceholder={t('İlçe ara…')}
                    options={[
                        ...visible.map((d) => ({
                            value: d.name,
                            label: d.name,
                        })),
                        ...(district &&
                        !visible.some((d) => d.name === district)
                            ? [{ value: district, label: district }]
                            : []),
                    ]}
                    onChange={setDistrict}
                />
                <InputError message={districtError} />
            </Field>
        </>
    );
}

/** Bayi paneli görünümü (shadcn + sayfa kalıpları). */
export const appKit: CheckoutKit = {
    Step: ({ title, step, children }) => (
        <SectionCard
            title={
                <span className="flex items-center gap-2">
                    <span className="grid size-6 place-items-center rounded-full bg-primary text-xs text-primary-foreground tabular-nums">
                        {step}
                    </span>
                    {title}
                </span>
            }
        >
            {children}
        </SectionCard>
    ),
    TextField: ({ label, error, className, ...props }) => (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={fieldId(props.name)}>
                {label}
                {props.required ? (
                    <span className="text-destructive">*</span>
                ) : null}
            </FieldLabel>
            <Input
                id={fieldId(props.name)}
                {...props}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    ),
    TextAreaField: ({ label, error, className, ...props }) => (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={fieldId(props.name)}>
                {label}
                {props.required ? (
                    <span className="text-destructive">*</span>
                ) : null}
            </FieldLabel>
            <Textarea
                id={fieldId(props.name)}
                {...props}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    ),
    CheckField: ({
        label,
        error,
        name,
        value,
        required,
        checked,
        onCheckedChange,
    }) => (
        <div className="flex flex-col gap-1">
            <label className="flex cursor-pointer items-start gap-2 text-sm">
                <Checkbox
                    name={name}
                    value={value}
                    required={required}
                    checked={checked}
                    onCheckedChange={
                        onCheckedChange
                            ? (v) => onCheckedChange(v === true)
                            : undefined
                    }
                    className="mt-0.5"
                />
                <span>{label}</span>
            </label>
            <InputError message={error} />
        </div>
    ),
    Choice: ({ name, value, checked, onChange, card, children }) => (
        <label
            className={cn(
                'flex cursor-pointer items-center gap-2 text-sm',
                card &&
                    'gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/40',
                card &&
                    checked &&
                    'border-primary bg-primary/5 ring-1 ring-primary',
            )}
        >
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                onChange={onChange}
                className="size-4 accent-primary"
            />
            {children}
        </label>
    ),
    LocationSelects: AppLocationSelects,
    SavedAddressSelect: ({ addresses, value, onChange, label }) =>
        addresses.length === 0 ? null : (
            <Field className="mb-4">
                <FieldLabel>{label}</FieldLabel>
                <SearchSelect
                    value={value}
                    onChange={onChange}
                    options={[
                        { value: '', label: t('Yeni adres gir') },
                        ...addresses.map((a) => ({
                            value: a.id,
                            label: `${a.title} — ${a.name}, ${a.district ?? ''}/${a.city ?? ''}`,
                        })),
                    ]}
                />
            </Field>
        ),
    Note: ({ tone = 'muted', children }) => (
        <p
            className={cn(
                'mt-2 text-xs',
                tone === 'error' ? 'text-destructive' : 'text-muted-foreground',
            )}
        >
            {children}
        </p>
    ),
    AddressPreview: ({ children }) => (
        <div className="rounded-lg border border-dashed bg-muted/40 p-3 text-sm">
            {children}
        </div>
    ),
    Divider: () => <Separator className="my-4" />,
    Summary: ({ children }) => (
        <SectionCard title={t('Sipariş özeti')}>{children}</SectionCard>
    ),
    SummaryLines: ({ lines }) => (
        <ul className="mb-4 flex max-h-60 flex-col gap-2 overflow-y-auto text-sm">
            {lines.map((l, i) => (
                <li key={i} className="flex justify-between gap-3">
                    <span className="min-w-0">
                        <span className="line-clamp-2">
                            {l.quantity} × {l.name}
                        </span>
                        {l.campaign ? (
                            <span className="mt-0.5 inline-block rounded-sm bg-primary/10 px-1.5 py-0.5 text-[11px] font-semibold text-primary">
                                {l.campaign}
                            </span>
                        ) : null}
                    </span>
                    <span className="shrink-0 font-medium tabular-nums">
                        {l.total_formatted}
                    </span>
                </li>
            ))}
        </ul>
    ),
    Totals: ({ totals, carrier, shippingFormatted, totalFormatted }) => (
        <div className="border-t pt-3">
            <TotalsList
                rows={[
                    ...(isPositive(totals.discount) && totals.discount_formatted
                        ? [
                              {
                                  label: t('Kampanya indirimi (KDV hariç)'),
                                  value: (
                                      <span className="text-green-700 dark:text-green-400">
                                          −{totals.discount_formatted}
                                      </span>
                                  ),
                              },
                          ]
                        : []),
                    {
                        label: t('Ara toplam (KDV hariç)'),
                        value: totals.subtotal_formatted,
                    },
                    { label: t('KDV'), value: totals.tax_formatted },
                    {
                        label: carrier
                            ? t('Kargo (:carrier)', { carrier: carrier.name })
                            : t('Kargo'),
                        value: shippingFormatted,
                    },
                ]}
                total={{ label: t('Genel toplam'), value: totalFormatted }}
            />
        </div>
    ),
    SubmitButton: ({ processing, className, children }) => (
        <Button
            type="submit"
            size="lg"
            disabled={processing}
            className={className}
        >
            {processing ? <Spinner /> : null}
            {children}
        </Button>
    ),
    CarrierLogo: ({ carrier }) =>
        carrier.logo ? (
            <img
                src={carrier.logo}
                alt=""
                className="h-7 w-12 object-contain"
            />
        ) : (
            <Truck className="size-6 text-muted-foreground" />
        ),
    linkClass: 'font-medium text-primary underline underline-offset-2',
    gridClass:
        'grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]',
    asideClass: 'lg:sticky lg:top-20 lg:self-start',
};
