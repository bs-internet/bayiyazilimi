import { Form } from '@inertiajs/react';
import { Save, X } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { payment } from '@/routes/panel/shop/orders';
import type { ShopOption } from '@/types';

import { SelectInput } from './select-input';
import type { OrderDetail } from './types';

/** Siparişin ödeme durumunu elle değiştirme (havale/EFT onayı). */
export function OrderPaymentDialog({
    order,
    paymentStatuses,
    formKey,
    open,
    onOpenChange,
}: {
    order: OrderDetail;
    paymentStatuses: ShopOption[];
    formKey: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Ödeme durumu')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Havale/EFT onayı için &quot;Ödendi&quot; seçin; sipariş hazırlanıyor durumuna geçer.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    key={formKey}
                    {...payment.form(order.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                data-invalid={Boolean(errors.payment_status)}
                            >
                                <FieldLabel htmlFor="payment_status">
                                    {t('Ödeme durumu')}
                                </FieldLabel>
                                <SelectInput
                                    id="payment_status"
                                    name="payment_status"
                                    options={paymentStatuses}
                                    defaultValue={order.payment_status}
                                />
                                {order.paid_at && (
                                    <FieldDescription>
                                        {t('Ödeme tarihi: :date', {
                                            date: formatDateTime(order.paid_at),
                                        })}
                                    </FieldDescription>
                                )}
                                <InputError message={errors.payment_status} />
                            </Field>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <X data-icon="inline-start" />
                                        {t('Vazgeç')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {t('Kaydet')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
