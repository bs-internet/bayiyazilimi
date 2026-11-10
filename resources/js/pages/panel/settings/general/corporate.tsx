import { Form, Head, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import ImageUpload from '@/components/image-upload';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';

import { TextareaField, TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import {
    cities as citiesRoute,
    districts as districtsRoute,
} from '@/routes/locations';
import { update } from '@/routes/panel/shop/corporate';
import {
    destroy as etbisDestroy,
    upload as etbisUpload,
} from '@/routes/panel/shop/corporate/etbis';

type Props = {
    settings: Record<string, string | null>;
    etbisQrUrl: string | null;
    /** Vitrin kurulu mu? ETBİS kare kodu yalnız o zaman çıkar. */
    storefront: boolean;
    countries: { id: string; name: string }[];
};

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-6 border-b py-7 last:border-b-0 lg:grid-cols-[240px_1fr]">
            <div className="flex flex-col gap-1">
                <h2 className="text-sm font-medium">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <div className="min-w-0">
                <FieldGroup className="gap-5">{children}</FieldGroup>
            </div>
        </section>
    );
}

type Option = { id: string; name: string };

/** İl / ilçe listelerini sunucudan çeker (ülke → iller, il → ilçeler). */
function useOptions(url: string | null): Option[] {
    const [options, setOptions] = useState<Option[]>([]);

    useEffect(() => {
        if (!url) {
            return;
        }

        const controller = new AbortController();

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data: Option[]) => setOptions(data))
            .catch(() => undefined);

        return () => controller.abort();
    }, [url]);

    return url ? options : [];
}

function AddressPlace({
    countries,
    countryId,
    cityId,
    districtId,
    errors,
}: {
    countries: Option[];
    countryId: string | null;
    cityId: string | null;
    districtId: string | null;
    errors: Record<string, string>;
}) {
    const [country, setCountry] = useState(countryId ?? '');
    const [city, setCity] = useState(cityId ?? '');
    const [district, setDistrict] = useState(districtId ?? '');
    const cities = useOptions(country ? citiesRoute(country).url : null);
    const districts = useOptions(city ? districtsRoute(city).url : null);
    const toOptions = (items: Option[]) =>
        items.map((item) => ({ value: item.id, label: item.name }));

    return (
        <div className="grid gap-5 md:grid-cols-3">
            <Field data-invalid={Boolean(errors.store_country_id)}>
                <FieldLabel htmlFor="store_country_id">{t('Ülke')}</FieldLabel>
                <SearchSelect
                    id="store_country_id"
                    name="store_country_id"
                    value={country}
                    required
                    placeholder={t('Ülke seçin')}
                    options={toOptions(countries)}
                    onChange={(next) => {
                        setCountry(next);
                        setCity('');
                        setDistrict('');
                    }}
                />
                <InputError message={errors.store_country_id} />
            </Field>
            <Field data-invalid={Boolean(errors.store_city_id)}>
                <FieldLabel htmlFor="store_city_id">{t('İl')}</FieldLabel>
                <SearchSelect
                    id="store_city_id"
                    name="store_city_id"
                    value={city}
                    required
                    disabled={!country}
                    placeholder={t('İl seçin')}
                    searchPlaceholder={t('İl ara…')}
                    options={toOptions(cities)}
                    onChange={(next) => {
                        setCity(next);
                        setDistrict('');
                    }}
                />
                <InputError message={errors.store_city_id} />
            </Field>
            <Field data-invalid={Boolean(errors.store_district_id)}>
                <FieldLabel htmlFor="store_district_id">{t('İlçe')}</FieldLabel>
                <SearchSelect
                    id="store_district_id"
                    name="store_district_id"
                    value={district}
                    required
                    disabled={!city}
                    placeholder={t('İlçe seçin')}
                    searchPlaceholder={t('İlçe ara…')}
                    options={toOptions(districts)}
                    onChange={setDistrict}
                />
                <InputError message={errors.store_district_id} />
            </Field>
        </div>
    );
}

/** ETBİS kare kodu: seçilir seçilmez yüklenir / silinir (formun Kaydet'inden bağımsız). */
function EtbisQr({ currentUrl }: { currentUrl: string | null }) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    return (
        <div className="max-w-xs">
            <ImageUpload
                title={t('Kare kod')}
                description={t(
                    "ETBİS'ten indirdiğiniz kare kod görseli (JPG / PNG, en çok 1 MB).",
                )}
                currentUrl={currentUrl}
                processing={processing}
                error={error}
                onUpload={(file) =>
                    router.post(
                        etbisUpload().url,
                        { file },
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            only: ['etbisQrUrl'],
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setError(''),
                            onError: (errors) =>
                                setError(
                                    errors.file ?? t('Görsel yüklenemedi.'),
                                ),
                        },
                    )
                }
                onRemove={() =>
                    router.delete(etbisDestroy().url, {
                        preserveScroll: true,
                        only: ['etbisQrUrl'],
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    })
                }
            />
        </div>
    );
}

export default function CorporateInfo({
    settings: s,
    etbisQrUrl,
    storefront,
    countries,
}: Props) {
    return (
        <>
            <Head title={t('Kurumsal Bilgiler')} />

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="max-w-5xl"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                            <div className="min-w-0">
                                <h1 className="text-xl font-semibold">
                                    {t('Kurumsal bilgiler')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Sitede, e-postalarda ve belgelerde görünen firma ünvanı, vergi bilgileri ve adres.',
                                    )}
                                </p>
                            </div>
                        </div>

                        <Section
                            title={t('Firma')}
                            description={t(
                                'E-postalarda ve belgelerde görünen firma ünvanı, vergi ve MERSİS bilgileri, e-posta ve telefon.',
                            )}
                        >
                            <TextField
                                name="store_name"
                                label={t('Firma Ünvanı')}
                                required
                                defaultValue={s.store_name}
                                error={errors.store_name}
                            />
                            <div className="grid gap-5 md:grid-cols-3">
                                <TextField
                                    name="tax_office"
                                    label={t('Vergi dairesi')}
                                    defaultValue={s.tax_office}
                                    error={errors.tax_office}
                                />
                                <TextField
                                    name="tax_number"
                                    label={t('Vergi no')}
                                    defaultValue={s.tax_number}
                                    error={errors.tax_number}
                                />
                                <TextField
                                    name="mersis_number"
                                    label={t('MERSİS no')}
                                    defaultValue={s.mersis_number}
                                    error={errors.mersis_number}
                                />
                            </div>
                            <div className="grid gap-5 md:grid-cols-2">
                                <TextField
                                    name="store_email"
                                    label={t('E-posta')}
                                    type="email"
                                    defaultValue={s.store_email}
                                    error={errors.store_email}
                                />
                                <TextField
                                    name="store_phone"
                                    label={t('Telefon')}
                                    defaultValue={s.store_phone}
                                    error={errors.store_phone}
                                />
                            </div>
                        </Section>

                        <Section
                            title={t('Adres')}
                            description={t(
                                'Firmanın resmi adresi; sitede, iletişim sayfasında ve belgelerde tek satır olarak görünür.',
                            )}
                        >
                            <AddressPlace
                                countries={countries}
                                countryId={s.store_country_id}
                                cityId={s.store_city_id}
                                districtId={s.store_district_id}
                                errors={errors}
                            />
                            <TextareaField
                                name="store_address"
                                label={t('Açık adres')}
                                rows={2}
                                defaultValue={s.store_address}
                                error={errors.store_address}
                                description={t(
                                    'Mahalle, cadde / sokak, bina ve kapı no.',
                                )}
                            />
                            <TextField
                                name="store_postcode"
                                label={t('Posta kodu')}
                                className="max-w-48"
                                defaultValue={s.store_postcode}
                                error={errors.store_postcode}
                            />
                        </Section>

                        {storefront ? (
                            <Section
                                title={t('ETBİS')}
                                description={t(
                                    'Elektronik Ticaret Bilgi Sistemi kaydınızın kare kodu; vitrinin alt bilgisinde gösterilir.',
                                )}
                            >
                                <EtbisQr currentUrl={etbisQrUrl} />
                                <TextField
                                    name="etbis_url"
                                    label={t('Doğrulama bağlantısı')}
                                    defaultValue={s.etbis_url}
                                    error={errors.etbis_url}
                                    placeholder="https://etbis.ticaret.gov.tr/..."
                                    description={t(
                                        'İsteğe bağlı. Girilirse kare koda tıklayan ziyaretçi bu adrese gider.',
                                    )}
                                />
                            </Section>
                        ) : null}

                        <div className="flex justify-end pt-5">
                            <Button disabled={processing}>
                                <Save data-icon="inline-start" />
                                {t('Kaydet')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

CorporateInfo.layout = {
    breadcrumbs: [
        {
            title: t('Kurumsal Bilgiler'),
            href: dashboard(),
        },
    ],
};
