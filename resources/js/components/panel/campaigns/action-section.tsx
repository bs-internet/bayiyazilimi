import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { ProductPicker } from '@/components/shop/product-picker';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { t } from '@/lib/i18n';
import { TiersInput } from './tiers-input';
import type { ShippingMode, SectionProps } from './types';
import { SHIPPING_ACTION, defaultSettings } from './types';

/** İşlem, ayarları, para birimi ve kargo. */
export function ActionSection(props: SectionProps) {
    const {
        form,
        data,
        setData,
        errors,
        actions,
        action,
        products,
        rememberProducts,
        hasMoney,
        symbolOf,
        currencies,
        baseCurrency,
    } = props;

    return (
        <SectionCard title={t('İşlem')}>
            <FieldGroup>
                <div className="grid items-start gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Field data-invalid={Boolean(errors.action)}>
                        <FieldLabel>{t('İşlem türü')}</FieldLabel>
                        <SearchSelect
                            aria-label={t('İşlem türü')}
                            value={data.action}
                            onChange={(key) =>
                                form.setData((d) => ({
                                    ...d,
                                    action: key,
                                    shipping_mode:
                                        key === SHIPPING_ACTION &&
                                        d.shipping_mode === 'cart'
                                            ? 'free'
                                            : d.shipping_mode,
                                    settings: defaultSettings(
                                        actions.find((a) => a.key === key),
                                    ),
                                }))
                            }
                            options={actions.map((a) => ({
                                value: a.key,
                                label: a.label,
                            }))}
                        />
                        <InputError message={errors.action} />
                    </Field>

                    {hasMoney ? (
                        <Field data-invalid={Boolean(errors.currency_code)}>
                            <FieldLabel>{t('Para birimi')}</FieldLabel>
                            <SearchSelect
                                aria-label={t('Para birimi')}
                                value={data.currency_code}
                                onChange={(v) => setData('currency_code', v)}
                                options={currencies}
                            />
                            <FieldDescription>
                                {t(
                                    'Sepet başka para birimindeyse kurla çevrilir.',
                                )}
                            </FieldDescription>
                            <InputError message={errors.currency_code} />
                        </Field>
                    ) : null}

                    {action && action.fields.length > 0 ? (
                        <>
                            {action.fields.map((field) => {
                                const error = errors[`settings.${field.key}`];
                                // Kademe satırlarının hataları (settings.tiers.0.min …) tek iletide.
                                const tierError = Object.entries(errors).find(
                                    ([k]) =>
                                        k.startsWith(`settings.${field.key}.`),
                                )?.[1];
                                const value = data.settings[field.key] ?? '';
                                const set = (v: string) =>
                                    setData('settings', {
                                        ...data.settings,
                                        [field.key]: v,
                                    });

                                return (
                                    <Field
                                        key={field.key}
                                        data-invalid={Boolean(error)}
                                        className={
                                            field.type === 'product' ||
                                            field.type === 'tiers'
                                                ? 'md:col-span-2 lg:col-span-3'
                                                : undefined
                                        }
                                    >
                                        <FieldLabel>{field.label}</FieldLabel>
                                        {field.type === 'number' ||
                                        field.type === 'money' ? (
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step="any"
                                                    value={value}
                                                    onChange={(e) =>
                                                        set(e.target.value)
                                                    }
                                                    aria-invalid={Boolean(
                                                        error,
                                                    )}
                                                />
                                                {field.type === 'money' ||
                                                field.suffix ? (
                                                    <span className="text-sm text-muted-foreground">
                                                        {field.type === 'money'
                                                            ? symbolOf(
                                                                  data.currency_code,
                                                              )
                                                            : field.suffix}
                                                    </span>
                                                ) : null}
                                            </div>
                                        ) : field.type === 'tiers' ? (
                                            <TiersInput
                                                value={value}
                                                onChange={set}
                                                unit={
                                                    data.settings.basis ===
                                                    'cart_total'
                                                        ? symbolOf(
                                                              data.currency_code,
                                                          )
                                                        : t('adet')
                                                }
                                                invalid={Boolean(
                                                    error ?? tierError,
                                                )}
                                            />
                                        ) : field.type === 'select' ? (
                                            <SearchSelect
                                                value={value}
                                                onChange={set}
                                                options={field.options ?? []}
                                            />
                                        ) : (
                                            <ProductPicker
                                                value={
                                                    value && products[value]
                                                        ? [products[value]]
                                                        : []
                                                }
                                                onChange={(picked) => {
                                                    // Tek ürün: son seçilen geçerli.
                                                    const last = picked.at(-1);
                                                    rememberProducts(picked);
                                                    set(last?.id ?? '');
                                                }}
                                            />
                                        )}
                                        {field.help ? (
                                            <FieldDescription>
                                                {field.help}
                                            </FieldDescription>
                                        ) : null}
                                        <InputError
                                            message={error ?? tierError}
                                        />
                                    </Field>
                                );
                            })}
                        </>
                    ) : null}
                </div>

                <Field
                    data-invalid={Boolean(
                        errors.shipping_mode || errors.shipping_amount,
                    )}
                >
                    <FieldLabel>{t('Kargo')}</FieldLabel>
                    <RadioGroup
                        value={data.shipping_mode}
                        onValueChange={(v) =>
                            setData('shipping_mode', v as ShippingMode)
                        }
                        className="flex min-h-9 flex-wrap items-center gap-6"
                    >
                        {(
                            [
                                ['cart', t('Sepete göre hesaplansın')],
                                ['free', t('Ücretsiz')],
                                ['fixed', t('Sabit ücret')],
                            ] as [ShippingMode, string][]
                        )
                            .filter(
                                ([mode]) =>
                                    mode !== 'cart' ||
                                    data.action !== SHIPPING_ACTION,
                            )
                            .map(([mode, label]) => (
                                <label
                                    key={mode}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <RadioGroupItem value={mode} />
                                    {label}
                                </label>
                            ))}
                        {data.shipping_mode === 'fixed' ? (
                            <div className="flex w-56 items-center gap-2">
                                <Input
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={data.shipping_amount}
                                    onChange={(e) =>
                                        setData(
                                            'shipping_amount',
                                            e.target.value,
                                        )
                                    }
                                    aria-label={t('Sabit kargo ücreti')}
                                    aria-invalid={Boolean(
                                        errors.shipping_amount,
                                    )}
                                />
                                <span className="text-sm whitespace-nowrap text-muted-foreground">
                                    {t(':base_currency (KDV dahil)', {
                                        base_currency: symbolOf(baseCurrency),
                                    })}
                                </span>
                            </div>
                        ) : null}
                    </RadioGroup>
                    <FieldDescription>
                        {data.action === SHIPPING_ACTION
                            ? t(
                                  'Kargo kampanyası yalnız kargoyu değiştirir; indirim yoktur.',
                              )
                            : t(
                                  'Kampanya uygulandığında kargo: sepete göre (normal), ücretsiz ya da sabit ücret. Normal ücret daha düşükse o geçerlidir.',
                              )}
                    </FieldDescription>
                    <InputError
                        message={errors.shipping_mode ?? errors.shipping_amount}
                    />
                </Field>
            </FieldGroup>
        </SectionCard>
    );
}
