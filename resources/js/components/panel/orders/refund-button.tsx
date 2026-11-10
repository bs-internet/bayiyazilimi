import { Form } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { useState } from 'react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { refund as refundPayment } from '@/routes/panel/shop/payments';

import type { OrderPayment } from './types';

/** Kart ödemesinin sağlayıcı üzerinden (tam / kısmi) iadesi. */
export function RefundButton({ payment }: { payment: OrderPayment }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                size="sm"
                variant="ghost"
                className="mt-1 h-7 px-2 text-xs"
                onClick={() => setOpen(true)}
            >
                {t('İade et')}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Kart ödemesini iade et')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                ':method_label · :amount_formatted. İade sağlayıcıya iletilir; tamamı iade edilirse ödeme "İade Edildi" olur ve cari düzeltilir.',
                                {
                                    method_label: payment.method_label,
                                    amount_formatted: payment.amount_formatted,
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...refundPayment.form(payment.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="refund_amount">
                                        {t('İade tutarı')}
                                    </Label>
                                    <Input
                                        id="refund_amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={payment.amount}
                                        defaultValue={payment.amount}
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <X />
                                            {t('Vazgeç')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? <Spinner /> : <Save />}
                                        {t('İade et')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
