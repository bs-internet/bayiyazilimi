import { Form, Head, router } from '@inertiajs/react';
import { Clock3, Coins, Globe2, Languages, Percent, Save } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import SettingsController from '@/actions/App/Http/Controllers/Panel/Settings/General/SettingsController';
import ImageUpload from '@/components/image-upload';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import type { SearchSelectOption } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useBranding } from '@/hooks/use-branding';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { Country, Currency, Language, Tax } from '@/types';

type SettingsValues = {
    app_name?: string | null;
    app_slogan?: string | null;
    logo_path?: string | null;
    logo_url?: string | null;
    logo_dark_path?: string | null;
    logo_dark_url?: string | null;
    favicon_path?: string | null;
    favicon_url?: string | null;
    default_country_id?: string | null;
    default_currency_id?: string | null;
    default_tax_id?: string | null;
    default_language_code?: string | null;
    default_timezone?: string | null;
};

type ResourceCollection<T> = {
    data: T[];
};

type Props = {
    settings: SettingsValues;
    countries: ResourceCollection<Country>;
    currencies: ResourceCollection<Currency>;
    taxes: ResourceCollection<Tax>;
    languages: ResourceCollection<Language>;
    timezones: string[];
    /** Ana para birimi kilitli (ilk sipariş / cari hareketten sonra). */
    currencyLocked?: boolean;
};

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-6 border-b py-7 last:border-b-0 lg:grid-cols-[240px_1fr]">
            <div className="flex flex-col gap-1">
                <h2 className="text-sm font-medium">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <div className="min-w-0">{children}</div>
        </section>
    );
}

function SelectField({
    id,
    name,
    label,
    defaultValue,
    error,
    icon,
    options,
}: {
    id: string;
    name: string;
    label: string;
    defaultValue?: string | null;
    error?: string;
    icon: ReactNode;
    options: SearchSelectOption[];
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id} className="items-center">
                {icon}
                {label}
            </FieldLabel>
            <SearchSelect
                id={id}
                name={name}
                options={options}
                defaultValue={defaultValue}
                placeholder={t('Seçilmedi')}
                clearable
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    );
}

export default function Settings({
    settings,
    countries,
    currencies,
    taxes,
    languages,
    timezones,
    currencyLocked = false,
}: Props) {
    const branding = useBranding();
    const [assetErrors, setAssetErrors] = useState<Record<string, string>>({});
    const [processingKey, setProcessingKey] = useState<string | null>(null);

    function uploadAsset(key: string, file: File) {
        router.post(
            SettingsController.uploadAsset().url,
            { key, file },
            {
                forceFormData: true,
                preserveScroll: true,
                only: ['settings'],
                onStart: () => setProcessingKey(key),
                onFinish: () => setProcessingKey(null),
                onSuccess: () =>
                    setAssetErrors((prev) => ({ ...prev, [key]: '' })),
                onError: (errors) =>
                    setAssetErrors((prev) => ({
                        ...prev,
                        [key]:
                            errors.file ??
                            errors.key ??
                            t('Görsel yüklenemedi.'),
                    })),
            },
        );
    }

    function removeAsset(key: string) {
        router.delete(SettingsController.destroyAsset().url, {
            data: { key },
            preserveScroll: true,
            only: ['settings'],
            onStart: () => setProcessingKey(key),
            onFinish: () => setProcessingKey(null),
        });
    }

    return (
        <>
            <Head title={t('Ayarlar')} />

            <Form
                {...SettingsController.update.form()}
                options={{ preserveScroll: true }}
                encType="multipart/form-data"
                className="max-w-5xl"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                            <div className="min-w-0">
                                <h1 className="text-xl font-semibold">
                                    {t('Genel ayarlar')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Panel kimliği ve varsayılan sistem tercihleri.',
                                    )}
                                </p>
                            </div>
                        </div>

                        <Section
                            title={t('Kimlik')}
                            description={t(
                                'Panelin adı, kısa tanımı ve marka görselleri.',
                            )}
                        >
                            <FieldGroup className="gap-5">
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        data-invalid={Boolean(errors.app_name)}
                                    >
                                        <FieldLabel htmlFor="app_name">
                                            {t('Uygulama adı')}
                                        </FieldLabel>
                                        <Input
                                            id="app_name"
                                            name="app_name"
                                            defaultValue={
                                                settings.app_name ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.app_name,
                                            )}
                                            placeholder={t('Panel')}
                                        />
                                        <InputError message={errors.app_name} />
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            errors.app_slogan,
                                        )}
                                    >
                                        <FieldLabel htmlFor="app_slogan">
                                            {t('Slogan')}
                                        </FieldLabel>
                                        <Input
                                            id="app_slogan"
                                            name="app_slogan"
                                            defaultValue={
                                                settings.app_slogan ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.app_slogan,
                                            )}
                                            placeholder={t(
                                                'Kısa ve net bir açıklama',
                                            )}
                                        />
                                        <InputError
                                            message={errors.app_slogan}
                                        />
                                    </Field>
                                </div>

                                <div className="flex flex-col gap-4">
                                    <ImageUpload
                                        title={t('Logo')}
                                        description={t(
                                            'Açık tema ve genel kullanım.',
                                        )}
                                        currentUrl={settings.logo_url}
                                        fallbackUrl={branding.logo}
                                        error={assetErrors.logo_path}
                                        processing={
                                            processingKey === 'logo_path'
                                        }
                                        onUpload={(file) =>
                                            uploadAsset('logo_path', file)
                                        }
                                        onRemove={() =>
                                            removeAsset('logo_path')
                                        }
                                    />
                                    <ImageUpload
                                        title={t('Logo dark')}
                                        description={t('Koyu tema kullanımı.')}
                                        currentUrl={settings.logo_dark_url}
                                        fallbackUrl={branding.logo_dark}
                                        error={assetErrors.logo_dark_path}
                                        processing={
                                            processingKey === 'logo_dark_path'
                                        }
                                        onUpload={(file) =>
                                            uploadAsset('logo_dark_path', file)
                                        }
                                        onRemove={() =>
                                            removeAsset('logo_dark_path')
                                        }
                                    />
                                    <ImageUpload
                                        title={t('Favicon')}
                                        description={t(
                                            'Tarayıcı sekmesi ikonu.',
                                        )}
                                        currentUrl={settings.favicon_url}
                                        fallbackUrl={branding.favicon}
                                        error={assetErrors.favicon_path}
                                        processing={
                                            processingKey === 'favicon_path'
                                        }
                                        previewClassName="object-cover"
                                        onUpload={(file) =>
                                            uploadAsset('favicon_path', file)
                                        }
                                        onRemove={() =>
                                            removeAsset('favicon_path')
                                        }
                                    />
                                </div>
                            </FieldGroup>
                        </Section>

                        <Section
                            title={t('Varsayılanlar')}
                            description={t(
                                'Tanımlamalar bölümünden gelen sistem tercihleri.',
                            )}
                        >
                            <FieldGroup className="grid gap-5 md:grid-cols-2">
                                <SelectField
                                    id="default_country_id"
                                    name="default_country_id"
                                    label={t('Ülke')}
                                    defaultValue={settings.default_country_id}
                                    error={errors.default_country_id}
                                    icon={
                                        <Globe2 className="size-4 text-muted-foreground" />
                                    }
                                    options={countries.data.map((country) => ({
                                        value: country.id,
                                        label: `${country.name}`,
                                    }))}
                                />

                                <SelectField
                                    id="default_currency_id"
                                    name="default_currency_id"
                                    label={t('Para birimi')}
                                    defaultValue={settings.default_currency_id}
                                    error={errors.default_currency_id}
                                    icon={
                                        <Coins className="size-4 text-muted-foreground" />
                                    }
                                    options={currencies.data.map(
                                        (currency) => ({
                                            value: currency.id,
                                            label: `${currency.name} (${currency.code})`,
                                        }),
                                    )}
                                />
                                <p className="-mt-2 text-xs text-muted-foreground">
                                    {t(
                                        'Sistemin ana para birimidir: cari hesaplar, raporlar ve başlangıç ekranı bu birimle tutulur.',
                                    )}
                                    {currencyLocked &&
                                        t(
                                            ' İlk sipariş / cari hareket oluştuğu için artık değiştirilemez.',
                                        )}
                                </p>

                                <SelectField
                                    id="default_tax_id"
                                    name="default_tax_id"
                                    label={t('Vergi oranı')}
                                    defaultValue={settings.default_tax_id}
                                    error={errors.default_tax_id}
                                    icon={
                                        <Percent className="size-4 text-muted-foreground" />
                                    }
                                    options={taxes.data.map((tax) => ({
                                        value: tax.id,
                                        label: `${tax.name} (%${tax.rate})`,
                                    }))}
                                />

                                <SelectField
                                    id="default_language_code"
                                    name="default_language_code"
                                    label={t('Dil')}
                                    defaultValue={
                                        settings.default_language_code
                                    }
                                    error={errors.default_language_code}
                                    icon={
                                        <Languages className="size-4 text-muted-foreground" />
                                    }
                                    options={languages.data.map((language) => ({
                                        value: language.code,
                                        label: `${language.native_name} (${language.code})`,
                                    }))}
                                />

                                <SelectField
                                    id="default_timezone"
                                    name="default_timezone"
                                    label={t('Zaman dilimi')}
                                    defaultValue={settings.default_timezone}
                                    error={errors.default_timezone}
                                    icon={
                                        <Clock3 className="size-4 text-muted-foreground" />
                                    }
                                    options={timezones.map((timezone) => ({
                                        value: timezone,
                                        label: `${timezone}`,
                                    }))}
                                />
                            </FieldGroup>
                        </Section>

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

Settings.layout = {
    breadcrumbs: [
        {
            title: t('Ayarlar'),
            href: dashboard(),
        },
    ],
};
