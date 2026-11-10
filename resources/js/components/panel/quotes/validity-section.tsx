import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import type { SearchSelectOption } from '@/components/search-select';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ShopOption } from '@/types';

import type { QuoteData, QuoteForm } from './types';

/** Müşteri (yönetici teklifinde), para birimi (yeni teklifte) ve geçerlilik tarihleri. */
export function ValiditySection({
    quote,
    adminQuote,
    editable,
    customers,
    currencies,
    defaultCurrency,
    data,
    setData,
    errors,
}: {
    quote: QuoteData | null;
    adminQuote: boolean;
    editable: boolean;
    customers: SearchSelectOption[];
    currencies: ShopOption[];
    defaultCurrency: string;
    data: QuoteForm['data'];
    setData: QuoteForm['setData'];
    errors: QuoteForm['errors'];
}) {
    return (
        <SectionCard
            title={adminQuote ? t('Müşteri ve geçerlilik') : t('Geçerlilik')}
        >
            {/* Müşteri tam satır; para birimi (yeni teklifte) ve tarihler tek satır. */}
            <div
                className={cn(
                    'grid gap-4',
                    quote === null ? 'md:grid-cols-3' : 'md:grid-cols-2',
                )}
            >
                {adminQuote ? (
                    <Field
                        data-invalid={Boolean(errors.user_id)}
                        className="md:col-span-full"
                    >
                        <FieldLabel htmlFor="user_id">
                            {t('Müşteri / bayi')}
                        </FieldLabel>
                        <SearchSelect
                            id="user_id"
                            options={customers}
                            value={data.user_id}
                            onChange={(value) => setData('user_id', value)}
                            placeholder={t('Müşteri seçin')}
                            searchPlaceholder={t('Ad veya e-posta ara…')}
                            searchable
                            disabled={!editable}
                        />
                        <InputError message={errors.user_id} />
                    </Field>
                ) : null}
                {quote === null ? (
                    <Field data-invalid={Boolean(errors.currency_code)}>
                        <FieldLabel htmlFor="currency_code">
                            {t('Para birimi')}
                        </FieldLabel>
                        <SearchSelect
                            id="currency_code"
                            options={currencies}
                            value={data.currency_code}
                            onChange={(value) =>
                                setData(
                                    'currency_code',
                                    value || defaultCurrency,
                                )
                            }
                            disabled={data.items.length > 0}
                        />
                        <InputError message={errors.currency_code} />
                    </Field>
                ) : null}
                <Field data-invalid={Boolean(errors.valid_from)}>
                    <FieldLabel htmlFor="valid_from">
                        {t('Geçerlilik başlangıcı')}
                    </FieldLabel>
                    <Input
                        id="valid_from"
                        type="date"
                        value={data.valid_from}
                        disabled={!editable}
                        onChange={(e) => setData('valid_from', e.target.value)}
                    />
                    <InputError message={errors.valid_from} />
                </Field>
                <Field data-invalid={Boolean(errors.valid_until)}>
                    <FieldLabel htmlFor="valid_until">
                        {t('Geçerlilik bitişi')}
                    </FieldLabel>
                    <Input
                        id="valid_until"
                        type="date"
                        value={data.valid_until}
                        min={data.valid_from || undefined}
                        disabled={!editable}
                        onChange={(e) => setData('valid_until', e.target.value)}
                    />
                    <InputError message={errors.valid_until} />
                </Field>
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
                {t(
                    'Müşteri teklifi yalnız bu tarihler arasında onaylayıp ödeyebilir. Bitiş boşsa gönderimde varsayılan geçerlilik süresi uygulanır.',
                )}
                {quote === null && data.items.length > 0
                    ? t(' Para birimi, ürün ekledikten sonra değiştirilemez.')
                    : ''}
            </p>
        </SectionCard>
    );
}
