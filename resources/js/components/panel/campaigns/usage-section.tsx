import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { t } from '@/lib/i18n';

import { MultiValue } from './rule-row';
import type { SectionProps } from './types';

/** Kullanım sınırları, bütçe ve birlikte uygulanabilecek kampanyalar. */
export function UsageSection(props: SectionProps) {
    const {
        data,
        setData,
        errors,
        otherCampaigns,
        campaign,
        symbolOf,
        baseCurrency,
    } = props;

    return (
        <SectionCard
            title={t('Kullanım')}
            description={t(
                'Sınırlar boş bırakılırsa kampanya sınırsız uygulanır. Sepette normalde tek kampanya geçerlidir (müşteriye en çok kazandıran); birlikte kullanılacak kampanyalar aynı sepette birlikte uygulanır, seçim iki yönlüdür.',
            )}
        >
            <FieldGroup>
                <div className="grid items-start gap-4 md:grid-cols-3">
                    <Field data-invalid={Boolean(errors.usage_limit)}>
                        <FieldLabel htmlFor="usage_limit">
                            {t('Toplam kullanım sınırı')}
                        </FieldLabel>
                        <InputGroup>
                            <InputGroupInput
                                id="usage_limit"
                                type="number"
                                min={1}
                                value={data.usage_limit}
                                onChange={(e) =>
                                    setData('usage_limit', e.target.value)
                                }
                                placeholder={t('Sınırsız')}
                            />
                            <InputGroupAddon align="inline-end">
                                <InputGroupText>{t('sipariş')}</InputGroupText>
                            </InputGroupAddon>
                        </InputGroup>
                        <FieldDescription>
                            {t('Örn. ilk 100 sipariş.')}
                        </FieldDescription>
                        <InputError message={errors.usage_limit} />
                    </Field>
                    <Field
                        data-invalid={Boolean(errors.usage_limit_per_customer)}
                    >
                        <FieldLabel htmlFor="usage_limit_per_customer">
                            {t('Müşteri başına')}
                        </FieldLabel>
                        <InputGroup>
                            <InputGroupInput
                                id="usage_limit_per_customer"
                                type="number"
                                min={1}
                                value={data.usage_limit_per_customer}
                                onChange={(e) =>
                                    setData(
                                        'usage_limit_per_customer',
                                        e.target.value,
                                    )
                                }
                                placeholder={t('Sınırsız')}
                            />
                            <InputGroupAddon align="inline-end">
                                <InputGroupText>{t('kez')}</InputGroupText>
                            </InputGroupAddon>
                        </InputGroup>
                        <FieldDescription>
                            {t('Hesap ya da e-posta adresi başına.')}
                        </FieldDescription>
                        <InputError message={errors.usage_limit_per_customer} />
                    </Field>
                    <Field data-invalid={Boolean(errors.budget)}>
                        <FieldLabel htmlFor="budget">{t('Bütçe')}</FieldLabel>
                        <InputGroup>
                            <InputGroupInput
                                id="budget"
                                type="number"
                                min={0}
                                step="0.01"
                                value={data.budget}
                                onChange={(e) =>
                                    setData('budget', e.target.value)
                                }
                                placeholder={t('Sınırsız')}
                            />
                            <InputGroupAddon align="inline-end">
                                <InputGroupText>
                                    {symbolOf(baseCurrency)}
                                </InputGroupText>
                            </InputGroupAddon>
                        </InputGroup>
                        <FieldDescription>
                            {t(
                                'Müşterilere sağlanan toplam kazanç (indirim, hediye, kargo) bu tutara ulaşınca kampanya uygulanmaz.',
                            )}
                        </FieldDescription>
                        <InputError message={errors.budget} />
                    </Field>
                </div>
                {campaign ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Şimdiye kadar :count siparişte kullanıldı, toplam kazanç :value (iptal edilenler hariç).',
                            {
                                count: campaign.usage.count,
                                value: campaign.usage.value,
                            },
                        )}
                    </p>
                ) : null}
                <Field data-invalid={Boolean(errors.combinable)}>
                    <FieldLabel>
                        {t('Birlikte kullanılacak kampanyalar')}
                    </FieldLabel>
                    <MultiValue
                        value={data.combinable}
                        options={otherCampaigns}
                        onChange={(next) => setData('combinable', next)}
                        invalid={Boolean(errors.combinable)}
                    />
                    <FieldDescription>
                        {t(
                            'Aynı ürüne iki indirim düşerse toplamı satır tutarını geçemez; kargoda en avantajlısı uygulanır.',
                        )}
                    </FieldDescription>
                    <InputError
                        message={
                            errors.combinable ??
                            Object.entries(errors).find(([k]) =>
                                k.startsWith('combinable.'),
                            )?.[1]
                        }
                    />
                </Field>
            </FieldGroup>
        </SectionCard>
    );
}
