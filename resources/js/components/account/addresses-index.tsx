import { Form, router } from '@inertiajs/react';
import {
    FileText,
    MapPin,
    Pencil,
    Plus,
    Save,
    Star,
    Truck,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';

import { AccountPageHeader } from '@/components/account/page-header';
import type { AccountArea, AccountRoutes } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { districts as districtsRoute } from '@/routes/locations';

/** AddressController::present() biçimi. */
export type MemberAddress = {
    id: string;
    title: string;
    type: string;
    type_label: string;
    name: string;
    company: string | null;
    tax_office: string | null;
    tax_number: string | null;
    identity_number: string | null;
    phone: string | null;
    city_id: string | null;
    city: string | null;
    district_id: string | null;
    district: string | null;
    address: string;
    postal_code: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

type Option = { id: string; name: string };

/** Her adres ya teslimat ya da fatura adresidir. */
type Kind = 'shipping' | 'billing';

export type AddressesIndexProps = {
    addresses: MemberAddress[];
    cities: Option[];
    types: { value: string; label: string }[];
};

const KIND = {
    shipping: {
        section: t('Teslimat adreslerim'),
        sectionHint: t('Siparişlerinizin gönderileceği adresler.'),
        newLabel: t('Yeni teslimat adresi'),
        editTitle: t('Teslimat adresini düzenle'),
        defaultBadge: t('Varsayılan teslimat'),
        makeDefault: t('Varsayılan teslimat yap'),
        defaultCheck: t('Varsayılan teslimat adresim olsun'),
        copyCheck: t('Bu adresi fatura adresi olarak da kaydet'),
        copyHint: t(
            'Aynı bilgilerle ayrı bir fatura adresi kaydı oluşturulur.',
        ),
        defaultFlag: 'is_default_shipping',
        icon: Truck,
        empty: t('Henüz teslimat adresiniz yok.'),
    },
    billing: {
        section: t('Fatura adreslerim'),
        sectionHint: t(
            'Faturalarınızın düzenleneceği bireysel / kurumsal bilgiler.',
        ),
        newLabel: t('Yeni fatura adresi'),
        editTitle: t('Fatura adresini düzenle'),
        defaultBadge: t('Varsayılan fatura'),
        makeDefault: t('Varsayılan fatura yap'),
        defaultCheck: t('Varsayılan fatura adresim olsun'),
        copyCheck: t('Bu adresi teslimat adresi olarak da kaydet'),
        copyHint: t(
            'Aynı adresle ayrı bir teslimat adresi kaydı oluşturulur (vergi bilgileri teslimat adresine aktarılmaz).',
        ),
        defaultFlag: 'is_default_billing',
        icon: FileText,
        empty: t('Henüz fatura adresiniz yok.'),
    },
} as const;

function TextInput({
    name,
    label,
    defaultValue,
    error,
    required,
    type = 'text',
}: {
    name: string;
    label: string;
    defaultValue?: string | null;
    error?: string;
    required?: boolean;
    type?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={`address-${name}`}>{label}</FieldLabel>
            <Input
                id={`address-${name}`}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                required={required}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    );
}

function CityDistrict({
    cities,
    cityId,
    districtId,
    errors,
}: {
    cities: Option[];
    cityId: string | null;
    districtId: string | null;
    errors: Record<string, string>;
}) {
    const [city, setCity] = useState(cityId ?? '');
    const [district, setDistrict] = useState(districtId ?? '');
    const [options, setOptions] = useState<Option[]>([]);

    useEffect(() => {
        if (!city) {
            return;
        }

        const controller = new AbortController();

        fetch(districtsRoute(city).url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data: Option[]) => setOptions(data))
            .catch(() => undefined);

        return () => controller.abort();
    }, [city]);

    return (
        <div className="grid grid-cols-2 gap-3">
            <Field data-invalid={Boolean(errors.city_id)}>
                <FieldLabel htmlFor="address-city_id">{t('İl')}</FieldLabel>
                <SearchSelect
                    id="address-city_id"
                    name="city_id"
                    value={city}
                    required
                    aria-invalid={Boolean(errors.city_id)}
                    placeholder={t('İl seçin')}
                    searchPlaceholder={t('İl ara…')}
                    options={cities.map((c) => ({
                        value: c.id,
                        label: c.name,
                    }))}
                    onChange={(next) => {
                        setCity(next);
                        setDistrict('');
                        setOptions([]);
                    }}
                />
                <InputError message={errors.city_id} />
            </Field>
            <Field data-invalid={Boolean(errors.district_id)}>
                <FieldLabel htmlFor="address-district_id">
                    {t('İlçe')}
                </FieldLabel>
                <SearchSelect
                    id="address-district_id"
                    name="district_id"
                    value={district}
                    required
                    disabled={!city}
                    aria-invalid={Boolean(errors.district_id)}
                    placeholder={t('İlçe seçin')}
                    searchPlaceholder={t('İlçe ara…')}
                    options={(city ? options : []).map((d) => ({
                        value: d.id,
                        label: d.name,
                    }))}
                    onChange={setDistrict}
                />
                <InputError message={errors.district_id} />
            </Field>
        </div>
    );
}

function CheckRow({
    checked,
    onChange,
    label,
    hint,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <label className="flex cursor-pointer items-start gap-2 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
                className="mt-0.5"
            />
            <span>
                {label}
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}

function AddressForm({
    routes,
    kind,
    address,
    cities,
    onDone,
}: {
    routes: AccountRoutes;
    kind: Kind;
    address: MemberAddress | null;
    cities: Option[];
    onDone: () => void;
}) {
    const copy = KIND[kind];
    const [corporate, setCorporate] = useState(Boolean(address?.company));
    const [makeDefault, setMakeDefault] = useState(
        address ? address[copy.defaultFlag] : false,
    );
    const [alsoOther, setAlsoOther] = useState(false);
    const action = address
        ? routes.addresses.update.form(address.id)
        : routes.addresses.store.form();

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="type" value={kind} />
                    <FieldGroup>
                        <TextInput
                            name="title"
                            label={t('Adres başlığı')}
                            defaultValue={
                                address?.title ??
                                (kind === 'billing' ? t('Fatura') : t('Merkez'))
                            }
                            error={errors.title}
                            required
                        />
                        {kind === 'billing' && (
                            <div className="flex gap-2">
                                {(
                                    [
                                        [false, 'Bireysel'],
                                        [true, 'Kurumsal'],
                                    ] as const
                                ).map(([value, label]) => (
                                    <Button
                                        key={label}
                                        type="button"
                                        size="sm"
                                        variant={
                                            corporate === value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setCorporate(value)}
                                    >
                                        {label}
                                    </Button>
                                ))}
                            </div>
                        )}
                        <TextInput
                            name="name"
                            label={
                                kind === 'billing'
                                    ? t('Ad soyad')
                                    : t('Teslim alacak kişi')
                            }
                            defaultValue={address?.name}
                            error={errors.name}
                            required
                        />
                        <TextInput
                            name="phone"
                            label={t('Telefon')}
                            type="tel"
                            defaultValue={address?.phone}
                            error={errors.phone}
                            required
                        />
                        {kind === 'billing' &&
                            (corporate ? (
                                <>
                                    <TextInput
                                        name="company"
                                        label={t('Firma ünvanı')}
                                        defaultValue={address?.company}
                                        error={errors.company}
                                        required
                                    />
                                    <div className="grid grid-cols-2 gap-3">
                                        <TextInput
                                            name="tax_office"
                                            label={t('Vergi dairesi')}
                                            defaultValue={address?.tax_office}
                                            error={errors.tax_office}
                                            required
                                        />
                                        <TextInput
                                            name="tax_number"
                                            label={t('Vergi numarası')}
                                            defaultValue={address?.tax_number}
                                            error={errors.tax_number}
                                            required
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    {/* Bireysele geçince eski firma bilgileri temizlenir. */}
                                    <input
                                        type="hidden"
                                        name="company"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        name="tax_office"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        name="tax_number"
                                        value=""
                                    />
                                    <TextInput
                                        name="identity_number"
                                        label={t(
                                            'T.C. kimlik no (isteğe bağlı)',
                                        )}
                                        defaultValue={address?.identity_number}
                                        error={errors.identity_number}
                                    />
                                </>
                            ))}
                        <CityDistrict
                            cities={cities}
                            cityId={address?.city_id ?? null}
                            districtId={address?.district_id ?? null}
                            errors={errors}
                        />
                        <Field data-invalid={Boolean(errors.address)}>
                            <FieldLabel htmlFor="address-address">
                                {t('Adres')}
                            </FieldLabel>
                            <Textarea
                                id="address-address"
                                name="address"
                                rows={3}
                                defaultValue={address?.address ?? ''}
                                required
                            />
                            <InputError message={errors.address} />
                        </Field>
                        <TextInput
                            name="postal_code"
                            label={t('Posta kodu')}
                            defaultValue={address?.postal_code}
                            error={errors.postal_code}
                        />
                        <input
                            type="hidden"
                            name={copy.defaultFlag}
                            value={makeDefault ? '1' : '0'}
                        />
                        <CheckRow
                            checked={makeDefault}
                            onChange={setMakeDefault}
                            label={copy.defaultCheck}
                        />
                        {!address && (
                            <>
                                <input
                                    type="hidden"
                                    name="also_as_other"
                                    value={alsoOther ? '1' : '0'}
                                />
                                <CheckRow
                                    checked={alsoOther}
                                    onChange={setAlsoOther}
                                    label={copy.copyCheck}
                                    hint={copy.copyHint}
                                />
                            </>
                        )}
                    </FieldGroup>
                    <FormActions
                        destroy={
                            address && (
                                <DeleteButton
                                    action={routes.addresses.destroy(
                                        address.id,
                                    )}
                                    title={t('":title" adresi silinsin mi?', {
                                        title: address.title,
                                    })}
                                    description={t('Bu işlem geri alınamaz.')}
                                />
                            )
                        }
                    >
                        <Button type="submit" disabled={processing}>
                            <Save data-icon="inline-start" />
                            {t('Kaydet')}
                        </Button>
                    </FormActions>
                </>
            )}
        </Form>
    );
}

function AddressSheet({
    routes,
    kind,
    address,
    cities,
    trigger,
}: {
    routes: AccountRoutes;
    kind: Kind;
    address: MemberAddress | null;
    cities: Option[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const copy = KIND[kind];

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>{trigger}</SheetTrigger>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {address ? copy.editTitle : copy.newLabel}
                    </SheetTitle>
                    <SheetDescription>
                        {kind === 'billing'
                            ? t(
                                  'Ödeme adımında fatura adresi olarak seçebilirsiniz.',
                              )
                            : t(
                                  'Ödeme adımında teslimat adresi olarak seçebilirsiniz.',
                              )}
                    </SheetDescription>
                </SheetHeader>
                {open && (
                    <AddressForm
                        routes={routes}
                        kind={kind}
                        address={address}
                        cities={cities}
                        onDone={() => setOpen(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function AddressSection({
    routes,
    kind,
    addresses,
    cities,
}: {
    routes: AccountRoutes;
    kind: Kind;
    addresses: MemberAddress[];
    cities: Option[];
}) {
    const copy = KIND[kind];
    const Icon = copy.icon;

    const makeDefault = (id: string) =>
        router.patch(
            routes.addresses.default(id).url,
            { kind },
            { preserveScroll: true },
        );

    const newButton = (
        <AddressSheet
            routes={routes}
            kind={kind}
            address={null}
            cities={cities}
            trigger={
                <Button size="sm" variant="outline">
                    <Plus data-icon="inline-start" />
                    {copy.newLabel}
                </Button>
            }
        />
    );

    return (
        <section className="flex flex-col gap-3" aria-label={copy.section}>
            <div className="flex flex-wrap items-end justify-between gap-2 border-b pb-2">
                <div>
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Icon className="size-4 text-muted-foreground" />
                        {copy.section}
                        <span className="text-sm font-normal text-muted-foreground tabular-nums">
                            ({addresses.length})
                        </span>
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {copy.sectionHint}
                    </p>
                </div>
                {newButton}
            </div>

            {addresses.length === 0 ? (
                <div className="flex flex-col items-start gap-1 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    <span className="flex items-center gap-2">
                        <MapPin className="size-4" />
                        {copy.empty}
                    </span>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {addresses.map((a) => {
                        const isDefault = a[copy.defaultFlag];

                        return (
                            <Card
                                key={a.id}
                                className={
                                    isDefault ? 'border-primary/50' : undefined
                                }
                            >
                                <CardHeader className="flex flex-row items-start justify-between gap-2">
                                    <div>
                                        <CardTitle className="text-base">
                                            {a.title}
                                        </CardTitle>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            {kind === 'billing' && (
                                                <Badge variant="outline">
                                                    {a.company
                                                        ? t('Kurumsal')
                                                        : t('Bireysel')}
                                                </Badge>
                                            )}
                                            {isDefault && (
                                                <Badge className="bg-primary/10 text-primary">
                                                    {copy.defaultBadge}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        <AddressSheet
                                            routes={routes}
                                            kind={kind}
                                            address={a}
                                            cities={cities}
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={t('Düzenle')}
                                                >
                                                    <Pencil />
                                                </Button>
                                            }
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-1 text-sm">
                                    <AddressLines address={a} />
                                    {!isDefault && (
                                        <div className="pt-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    makeDefault(a.id)
                                                }
                                            >
                                                <Star data-icon="inline-start" />
                                                {copy.makeDefault}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

/**
 * Adreslerim: teslimat ve fatura adresleri, aynı adres olsalar bile ayrı
 * bölümlerde ve ayrı kayıtlar olarak yönetilir.
 */
export function AddressesIndexContent({
    area,
    addresses,
    cities,
}: AddressesIndexProps & { area: AccountArea }) {
    const routes = accountRoutes(area);

    return (
        <>
            <AccountPageHeader
                title={t('Adreslerim')}
                description={t(
                    'Teslimat ve fatura adreslerinizi ayrı ayrı yönetin.',
                )}
            />

            <div className="flex flex-col gap-8">
                <AddressSection
                    routes={routes}
                    kind="shipping"
                    addresses={addresses.filter((a) => a.type === 'shipping')}
                    cities={cities}
                />
                <AddressSection
                    routes={routes}
                    kind="billing"
                    addresses={addresses.filter((a) => a.type === 'billing')}
                    cities={cities}
                />
            </div>
        </>
    );
}

/** Kayıtlı adresin metin satırları (adres kartı ve hesap özeti ortak). */
export function AddressLines({ address: a }: { address: MemberAddress }) {
    return (
        <>
            <div className="font-medium">{a.name}</div>
            {a.company && <div>{a.company}</div>}
            {a.tax_number && (
                <div className="text-muted-foreground">
                    {a.tax_office} · {a.tax_number}
                </div>
            )}
            {!a.company && a.identity_number && (
                <div className="text-muted-foreground">
                    T.C. {a.identity_number}
                </div>
            )}
            <div className="text-muted-foreground">{a.address}</div>
            <div className="text-muted-foreground">
                {[a.district, a.city].filter(Boolean).join(' / ')}
                {a.postal_code ? ` · ${a.postal_code}` : ''}
            </div>
            {a.phone && <div className="text-muted-foreground">{a.phone}</div>}
        </>
    );
}
