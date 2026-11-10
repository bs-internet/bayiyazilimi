import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { SectionProps } from './types';

/** Ad, etiket, tarih aralığı ve sıra. */
export function GeneralSection(props: SectionProps) {
    const { data, setData, errors, storefront } = props;

    return (
        <SectionCard title={t('Genel')}>
            <FieldGroup>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field data-invalid={Boolean(errors.name)}>
                        <FieldLabel htmlFor="name">
                            {t('Kampanya adı')}
                        </FieldLabel>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder={t(
                                'Örn. Zemin temizleyicilerde 2 Al 1 Öde',
                            )}
                        />
                        <FieldDescription>
                            {t('Sepette ve siparişte bu ad görünür.')}
                        </FieldDescription>
                        <InputError message={errors.name} />
                    </Field>
                    {storefront ? (
                        <Field data-invalid={Boolean(errors.badge)}>
                            <FieldLabel htmlFor="badge">
                                {t('Vitrin etiketi')}
                            </FieldLabel>
                            <Input
                                id="badge"
                                value={data.badge}
                                onChange={(e) =>
                                    setData('badge', e.target.value)
                                }
                                maxLength={40}
                                placeholder={t(
                                    'Boşsa işlemden: “2 Al 1 Öde”, “%10 İndirim”…',
                                )}
                            />
                            <FieldDescription>
                                {t(
                                    'Kampanyadaki ürünlerin kartında rozet olarak görünür.',
                                )}
                            </FieldDescription>
                            <InputError message={errors.badge} />
                        </Field>
                    ) : null}
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <Field data-invalid={Boolean(errors.starts_at)}>
                        <FieldLabel htmlFor="starts_at">
                            {t('Başlangıç')}
                        </FieldLabel>
                        <Input
                            id="starts_at"
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(e) =>
                                setData('starts_at', e.target.value)
                            }
                        />
                        <InputError message={errors.starts_at} />
                    </Field>
                    <Field data-invalid={Boolean(errors.ends_at)}>
                        <FieldLabel htmlFor="ends_at">{t('Bitiş')}</FieldLabel>
                        <Input
                            id="ends_at"
                            type="datetime-local"
                            value={data.ends_at}
                            onChange={(e) => setData('ends_at', e.target.value)}
                        />
                        <InputError message={errors.ends_at} />
                    </Field>
                    <Field data-invalid={Boolean(errors.sort_order)}>
                        <FieldLabel htmlFor="sort_order">
                            {t('Sıra')}
                        </FieldLabel>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={data.sort_order}
                            onChange={(e) =>
                                setData('sort_order', e.target.value)
                            }
                        />
                        <FieldDescription>
                            {t('Eşit kazançta küçük sıra öne geçer.')}
                        </FieldDescription>
                        <InputError message={errors.sort_order} />
                    </Field>
                </div>
            </FieldGroup>
        </SectionCard>
    );
}
