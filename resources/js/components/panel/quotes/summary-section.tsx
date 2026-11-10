import { Save, Send } from 'lucide-react';

import { SectionCard, TotalsList } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { isPositive } from '@/lib/decimal';
import type { DecimalInput } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { destroy } from '@/routes/panel/shop/quotes';

import type { QuoteTotals } from './lines';
import type { QuoteData, QuoteForm } from './types';

/** Kargo ücreti, müşteriye not, toplamlar ve kaydet / gönder düğmeleri. */
export function SummarySection({
    quote,
    editable,
    data,
    setData,
    errors,
    processing,
    totals,
    money,
    sendLabel,
    onSubmit: submit,
}: {
    quote: QuoteData | null;
    editable: boolean;
    data: QuoteForm['data'];
    setData: QuoteForm['setData'];
    errors: QuoteForm['errors'];
    processing: boolean;
    totals: QuoteTotals;
    money: (value: DecimalInput) => string;
    sendLabel: string;
    onSubmit: (send: boolean) => void;
}) {
    return (
        <SectionCard title={t('Kargo, not ve toplam')}>
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 md:grid-cols-[14rem_minmax(0,1fr)]">
                    <Field data-invalid={Boolean(errors.shipping_total)}>
                        <FieldLabel htmlFor="shipping_total">
                            {t('Kargo ücreti (KDV dahil, :currency_code)', {
                                currency_code: data.currency_code,
                            })}
                        </FieldLabel>
                        <Input
                            id="shipping_total"
                            inputMode="decimal"
                            value={data.shipping_total}
                            disabled={!editable}
                            onChange={(e) =>
                                setData('shipping_total', e.target.value)
                            }
                        />
                        <InputError message={errors.shipping_total} />
                    </Field>
                    <Field data-invalid={Boolean(errors.admin_note)}>
                        <FieldLabel htmlFor="admin_note">
                            {t('Müşteriye not')}
                        </FieldLabel>
                        <Textarea
                            id="admin_note"
                            rows={3}
                            value={data.admin_note}
                            disabled={!editable}
                            placeholder={t('Örn. Teslim süresi 5 iş günüdür.')}
                            onChange={(e) =>
                                setData('admin_note', e.target.value)
                            }
                        />
                        <InputError message={errors.admin_note} />
                    </Field>
                </div>
                <div className="ml-auto w-full max-w-sm">
                    <TotalsList
                        rows={[
                            {
                                label: t('Ara toplam (KDV hariç)'),
                                value: money(totals.subtotal),
                            },
                            {
                                label: t('KDV'),
                                value: money(totals.tax),
                            },
                            {
                                label: t('Kargo'),
                                value: isPositive(totals.shipping)
                                    ? money(totals.shipping)
                                    : t('Ücretsiz'),
                            },
                        ]}
                        total={{
                            label: t('Genel toplam'),
                            value: money(totals.total),
                        }}
                    />
                    {totals.unpriced > 0 ? (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                ':unpriced kalemde fiyat girilmedi; toplamda liste fiyatı kullanıldı. Göndermek için tüm fiyatları girin.',
                                { unpriced: totals.unpriced },
                            )}
                        </p>
                    ) : null}
                </div>
                {editable ? (
                    <FormActions
                        destroy={
                            quote?.can_delete ? (
                                <DeleteButton
                                    action={destroy(quote.id)}
                                    title={t(':number taslağı silinsin mi?', {
                                        number: quote.number,
                                    })}
                                    description={t(
                                        'Müşteriye hiç gönderilmedi. Bu işlem geri alınamaz.',
                                    )}
                                />
                            ) : null
                        }
                    >
                        {/* Gönderilmiş teklif yalnız yeniden gönderilerek değişir (müşterinin gördüğü fiyat sessizce değişmez). */}
                        {quote?.status !== 'sent' ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => submit(false)}
                            >
                                <Save data-icon="inline-start" />
                                {t('Kaydet')}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={() => submit(true)}
                        >
                            {processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Send data-icon="inline-start" />
                            )}
                            {sendLabel}
                        </Button>
                    </FormActions>
                ) : null}
            </div>
        </SectionCard>
    );
}
