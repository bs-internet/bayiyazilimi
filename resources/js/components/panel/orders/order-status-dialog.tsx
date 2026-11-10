import { Form } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { status } from '@/routes/panel/shop/orders';
import type { ShopOption } from '@/types';

import { SelectInput } from './select-input';
import type { OrderDetail } from './types';

/** Sipariş durumu + kargo firması / takip no + geçmiş notu diyaloğu. */
export function OrderStatusDialog({
    order,
    statuses,
    carriers,
    formKey,
    open,
    onOpenChange,
}: {
    order: OrderDetail;
    statuses: ShopOption[];
    carriers: ShopOption[];
    formKey: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [notify, setNotify] = useState(true);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Sipariş durumunu değiştir')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'İptal edildiğinde düşülen stoklar depolara geri eklenir.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    key={formKey}
                    {...status.form(order.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FieldGroup>
                                <Field data-invalid={Boolean(errors.status)}>
                                    <FieldLabel htmlFor="status">
                                        {t('Yeni durum')}
                                    </FieldLabel>
                                    <SelectInput
                                        id="status"
                                        name="status"
                                        options={statuses}
                                        defaultValue={order.status}
                                    />
                                    <InputError message={errors.status} />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={Boolean(
                                            errors.carrier_id,
                                        )}
                                    >
                                        <FieldLabel htmlFor="carrier_id">
                                            {t('Kargo firması')}
                                        </FieldLabel>
                                        <SelectInput
                                            id="carrier_id"
                                            name="carrier_id"
                                            options={carriers}
                                            placeholder={t('— Seçiniz —')}
                                            defaultValue={order.carrier_id}
                                        />
                                        <InputError
                                            message={errors.carrier_id}
                                        />
                                    </Field>
                                    <Field
                                        data-invalid={Boolean(
                                            errors.tracking_number,
                                        )}
                                    >
                                        <FieldLabel htmlFor="tracking_number">
                                            {t('Takip numarası')}
                                        </FieldLabel>
                                        <Input
                                            id="tracking_number"
                                            name="tracking_number"
                                            defaultValue={
                                                order.tracking_number ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.tracking_number}
                                        />
                                    </Field>
                                </div>
                                <Field data-invalid={Boolean(errors.note)}>
                                    <FieldLabel htmlFor="note">
                                        {t('Not')}
                                    </FieldLabel>
                                    <Input
                                        id="note"
                                        name="note"
                                        maxLength={255}
                                    />
                                    <FieldDescription>
                                        {t(
                                            'Geçmişe yazılır. Müşteriye durum e-postası her zaman gider; not yalnız aşağıdaki seçenekle eklenir.',
                                        )}
                                    </FieldDescription>
                                    <InputError message={errors.note} />
                                </Field>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="notify"
                                        value={notify ? '1' : '0'}
                                    />
                                    <Checkbox
                                        checked={notify}
                                        onCheckedChange={(v) =>
                                            setNotify(v === true)
                                        }
                                    />
                                    {t(
                                        'Notu müşteriye de göster (e-posta ve sipariş sayfası)',
                                    )}
                                </label>
                            </FieldGroup>
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
                                    {t('Durumu kaydet')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
