import { Form, Head, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useMemo, useState } from 'react';

import AccountController from '@/actions/App/Http/Controllers/App/Account/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxCollection,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { account as accountRoute } from '@/routes/app';
import type {
    Account as AccountType,
    BillingType,
    CityOption,
    CountryOption,
    DistrictOption,
} from '@/types';

type Props = {
    account: {
        data: AccountType | null;
    };
    countries: CountryOption[];
    cities: CityOption[];
    districts: DistrictOption[];
    billingTypes: { value: BillingType; label: string }[];
};

function reloadLocations(countryId: string, cityId: string) {
    router.reload({
        only: ['cities', 'districts'],
        data: { country_id: countryId, city_id: cityId },
    });
}

export default function Account({
    account,
    countries,
    cities,
    districts,
    billingTypes,
}: Props) {
    const currentAccount = account.data;
    const currentAddress = currentAccount?.address ?? null;
    const [billingType, setBillingType] = useState<BillingType>(
        currentAccount?.billing_type ?? 'individual',
    );
    const corporate = billingType === 'corporate';
    const [countryId, setCountryId] = useState(
        currentAddress?.country_id ?? '',
    );
    const [cityId, setCityId] = useState(currentAddress?.city_id ?? '');
    const [districtId, setDistrictId] = useState(
        currentAddress?.district_id ?? '',
    );

    const selectedCountry = useMemo(
        () => countries.find((c) => c.id === countryId) ?? null,
        [countries, countryId],
    );
    const selectedCity = useMemo(
        () => cities.find((c) => c.id === cityId) ?? null,
        [cities, cityId],
    );
    const selectedDistrict = useMemo(
        () => districts.find((d) => d.id === districtId) ?? null,
        [districts, districtId],
    );

    function onCountryChange(next: CountryOption | null) {
        const nextId = next?.id ?? '';
        setCountryId(nextId);
        setCityId('');
        setDistrictId('');
        reloadLocations(nextId, '');
    }

    function onCityChange(next: CityOption | null) {
        const nextId = next?.id ?? '';
        setCityId(nextId);
        setDistrictId('');
        reloadLocations(countryId, nextId);
    }

    function onDistrictChange(next: DistrictOption | null) {
        setDistrictId(next?.id ?? '');
    }

    return (
        <>
            <Head title={t('Hesabım')} />

            <div className="flex max-w-2xl flex-col gap-6">
                <Heading
                    variant="small"
                    title={t('Fatura bilgileri')}
                    description={t(
                        'Siparişlerinizde faturanın düzenleneceği bilgiler. Teslimat ve fatura adreslerinizi Adreslerim bölümünden yönetebilirsiniz.',
                    )}
                />

                {!currentAccount && (
                    <Alert>
                        <AlertDescription>
                            {t(
                                'Henüz fatura bilgisi girmediniz. Kaydettiğinizde hesabınız oluşturulur.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...AccountController.update.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="billing_type"
                                value={billingType}
                            />

                            <div className="grid gap-3">
                                <Label>{t('Fatura türü')}</Label>
                                <RadioGroup
                                    value={billingType}
                                    onValueChange={(value) =>
                                        setBillingType(value as BillingType)
                                    }
                                    className="grid gap-3 sm:grid-cols-2"
                                >
                                    {billingTypes.map((type) => (
                                        <Label
                                            key={type.value}
                                            htmlFor={`billing_type_${type.value}`}
                                            className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 font-normal has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                                        >
                                            <RadioGroupItem
                                                id={`billing_type_${type.value}`}
                                                value={type.value}
                                            />
                                            <span className="grid gap-1">
                                                <span className="font-medium">
                                                    {type.label}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {type.value === 'corporate'
                                                        ? t(
                                                              'Firma adına; vergi dairesi ve vergi no ile',
                                                          )
                                                        : t(
                                                              'Şahıs adına; TC kimlik no ile',
                                                          )}
                                                </span>
                                            </span>
                                        </Label>
                                    ))}
                                </RadioGroup>
                                <InputError message={errors.billing_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">
                                    {corporate
                                        ? t('Firma ünvanı')
                                        : t('Ad soyad')}
                                </Label>
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    autoComplete={
                                        corporate ? 'organization' : 'name'
                                    }
                                    defaultValue={currentAccount?.title ?? ''}
                                    aria-invalid={Boolean(errors.title)}
                                />
                                <InputError message={errors.title} />
                            </div>

                            {corporate ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_office">
                                            {t('Vergi dairesi')}
                                        </Label>
                                        <Input
                                            id="tax_office"
                                            name="tax_office"
                                            required
                                            defaultValue={
                                                currentAccount?.tax_office ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.tax_office,
                                            )}
                                        />
                                        <InputError
                                            message={errors.tax_office}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_number">
                                            {t('Vergi no')}
                                        </Label>
                                        <Input
                                            id="tax_number"
                                            name="tax_number"
                                            required
                                            inputMode="numeric"
                                            maxLength={11}
                                            defaultValue={
                                                currentAccount?.tax_number ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.tax_number,
                                            )}
                                        />
                                        <InputError
                                            message={errors.tax_number}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="identity_number">
                                        {t('TC kimlik no')}
                                    </Label>
                                    <Input
                                        id="identity_number"
                                        name="identity_number"
                                        required
                                        inputMode="numeric"
                                        maxLength={11}
                                        defaultValue={
                                            currentAccount?.identity_number ??
                                            ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.identity_number,
                                        )}
                                    />
                                    <InputError
                                        message={errors.identity_number}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="invoice_email">
                                    {t('E-fatura e-posta adresi')}
                                </Label>
                                <Input
                                    id="invoice_email"
                                    name="invoice_email"
                                    type="email"
                                    autoComplete="email"
                                    placeholder={t(
                                        'Boş bırakılırsa hesap e-postanız kullanılır',
                                    )}
                                    defaultValue={
                                        currentAccount?.invoice_email ?? ''
                                    }
                                    aria-invalid={Boolean(errors.invoice_email)}
                                />
                                <InputError message={errors.invoice_email} />
                            </div>

                            <Separator />

                            <div className="flex flex-col gap-1">
                                <h3 className="text-sm font-semibold">
                                    {t('Fatura adresi')}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {t('Hesabınıza kayıtlı resmi adres.')}
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="country_id">
                                        {t('Ülke')}
                                    </Label>
                                    <Combobox
                                        items={countries}
                                        value={selectedCountry}
                                        onValueChange={onCountryChange}
                                        itemToStringLabel={(c: CountryOption) =>
                                            c.name
                                        }
                                    >
                                        <ComboboxInput
                                            id="country_id"
                                            placeholder={t('Seçiniz')}
                                            showClear={Boolean(countryId)}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                <ComboboxEmpty>
                                                    {t('Sonuç yok')}
                                                </ComboboxEmpty>
                                                <ComboboxCollection>
                                                    {(
                                                        country: CountryOption,
                                                    ) => (
                                                        <ComboboxItem
                                                            key={country.id}
                                                            value={country}
                                                        >
                                                            {country.name}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxCollection>
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                    <input
                                        type="hidden"
                                        name="country_id"
                                        value={countryId}
                                    />
                                    <InputError message={errors.country_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="city_id">
                                        {t('Şehir')}
                                    </Label>
                                    <Combobox
                                        items={cities}
                                        value={selectedCity}
                                        onValueChange={onCityChange}
                                        itemToStringLabel={(c: CityOption) =>
                                            c.name
                                        }
                                        disabled={countryId === ''}
                                    >
                                        <ComboboxInput
                                            id="city_id"
                                            placeholder={t('Seçiniz')}
                                            disabled={countryId === ''}
                                            showClear={Boolean(cityId)}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                <ComboboxEmpty>
                                                    {t('Sonuç yok')}
                                                </ComboboxEmpty>
                                                <ComboboxCollection>
                                                    {(city: CityOption) => (
                                                        <ComboboxItem
                                                            key={city.id}
                                                            value={city}
                                                        >
                                                            {city.name}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxCollection>
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                    <input
                                        type="hidden"
                                        name="city_id"
                                        value={cityId}
                                    />
                                    <InputError message={errors.city_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="district_id">
                                        {t('İlçe')}
                                    </Label>
                                    <Combobox
                                        items={districts}
                                        value={selectedDistrict}
                                        onValueChange={onDistrictChange}
                                        itemToStringLabel={(
                                            d: DistrictOption,
                                        ) => d.name}
                                        disabled={cityId === ''}
                                    >
                                        <ComboboxInput
                                            id="district_id"
                                            placeholder={t('Seçiniz')}
                                            disabled={cityId === ''}
                                            showClear={Boolean(districtId)}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                <ComboboxEmpty>
                                                    {t('Sonuç yok')}
                                                </ComboboxEmpty>
                                                <ComboboxCollection>
                                                    {(
                                                        district: DistrictOption,
                                                    ) => (
                                                        <ComboboxItem
                                                            key={district.id}
                                                            value={district}
                                                        >
                                                            {district.name}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxCollection>
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                    <input
                                        type="hidden"
                                        name="district_id"
                                        value={districtId}
                                    />
                                    <InputError message={errors.district_id} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">{t('Adres')}</Label>
                                <Textarea
                                    id="address"
                                    name="address"
                                    defaultValue={currentAddress?.address ?? ''}
                                    rows={3}
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid gap-2 sm:max-w-48">
                                <Label htmlFor="postal_code">
                                    {t('Posta kodu')}
                                </Label>
                                <Input
                                    id="postal_code"
                                    name="postal_code"
                                    defaultValue={
                                        currentAddress?.postal_code ?? ''
                                    }
                                />
                                <InputError message={errors.postal_code} />
                            </div>

                            <div className="flex justify-end">
                                <Button disabled={processing}>
                                    {processing ? <Spinner /> : <Save />}
                                    {t('Kaydet')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Account.layout = {
    breadcrumbs: [
        {
            title: t('Hesabım'),
            href: accountRoute(),
        },
    ],
};
