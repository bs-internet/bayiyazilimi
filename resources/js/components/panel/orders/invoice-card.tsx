import { Form } from '@inertiajs/react';
import { Pencil, Save, X } from 'lucide-react';
import { useState } from 'react';

import { InfoList, SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { invoice } from '@/routes/panel/shop/orders';

import type { OrderDetail } from './types';

/**
 * Fatura takibi: tamamlanan siparişe fatura no girilir; girilmişse no ve tarih
 * gösterilir, düzenlenebilir ya da kaldırılabilir (boş kaydetmek kaldırır).
 */
export function InvoiceCard({ order }: { order: OrderDetail }) {
    const [editing, setEditing] = useState(false);
    const completed = order.status === 'completed';

    return (
        <SectionCard
            title={t('Fatura')}
            action={
                completed && order.invoice_number && !editing ? (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('Fatura bilgisini düzenle')}
                        onClick={() => setEditing(true)}
                    >
                        <Pencil />
                    </Button>
                ) : null
            }
        >
            {!completed ? (
                <p className="text-sm text-muted-foreground">
                    {t('Fatura bilgisi sipariş tamamlandıktan sonra girilir.')}
                </p>
            ) : order.invoice_number && !editing ? (
                <InfoList
                    items={[
                        { label: t('Fatura no'), value: order.invoice_number },
                        {
                            label: t('Faturalandırıldı'),
                            value: order.invoiced_at
                                ? formatDateTime(order.invoiced_at)
                                : '—',
                        },
                    ]}
                />
            ) : (
                <Form
                    {...invoice.form(order.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEditing(false)}
                    className="flex flex-col gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                data-invalid={Boolean(errors.invoice_number)}
                            >
                                <FieldLabel htmlFor="invoice_number">
                                    {t('Fatura no')}
                                </FieldLabel>
                                <Input
                                    id="invoice_number"
                                    name="invoice_number"
                                    maxLength={64}
                                    required={!order.invoice_number}
                                    defaultValue={order.invoice_number ?? ''}
                                    aria-invalid={Boolean(
                                        errors.invoice_number,
                                    )}
                                />
                                {order.invoice_number ? (
                                    <FieldDescription>
                                        {t(
                                            'Boş bırakıp kaydederseniz fatura bilgisi kaldırılır.',
                                        )}
                                    </FieldDescription>
                                ) : null}
                                <InputError message={errors.invoice_number} />
                            </Field>
                            <div className="flex justify-end gap-2">
                                {editing ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => setEditing(false)}
                                    >
                                        <X data-icon="inline-start" />
                                        {t('Vazgeç')}
                                    </Button>
                                ) : null}
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {order.invoice_number
                                        ? t('Kaydet')
                                        : t('Faturalandırıldı olarak işaretle')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </SectionCard>
    );
}
