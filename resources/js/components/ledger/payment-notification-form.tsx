import { Form } from '@inertiajs/react';
import { Send, X } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { store } from '@/routes/app/shop/payment-notifications';

export type PaymentNotifyData = {
    currency: string;
    banks: { value: string; label: string }[];
    orders: { value: string; label: string }[];
    history: {
        id: string;
        created_at: string | null;
        transfer_date: string;
        amount: string;
        order_number: string | null;
        status: string;
        status_label: string;
        reject_reason: string | null;
    }[];
};

const NONE = '__none';
const today = () => new Date().toISOString().slice(0, 10);

/**
 * Havale bildirimi (bayi): tutar, tarih, hesap, isteğe bağlı sipariş ve dekont.
 * Yönetici onaylayınca ödeme carinize işlenir.
 */
export function PaymentNotifyButton({ notify }: { notify: PaymentNotifyData }) {
    const [open, setOpen] = useState(false);
    const [bank, setBank] = useState(notify.banks[0]?.value ?? NONE);
    const [order, setOrder] = useState(NONE);

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Send data-icon="inline-start" />
                {t('Havale bildirimi yap')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('Havale / EFT bildirimi')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Yaptığınız ödemeyi dekontuyla bildirin; onaylanınca carinize işlenir.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        resetOnSuccess
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="bank_account_id"
                                    value={bank === NONE ? '' : bank}
                                />
                                <input
                                    type="hidden"
                                    name="order_id"
                                    value={order === NONE ? '' : order}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="notify_amount">
                                            {t('Tutar (:currency)', {
                                                currency: notify.currency,
                                            })}
                                        </Label>
                                        <Input
                                            id="notify_amount"
                                            name="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="notify_date">
                                            {t('Havale tarihi')}
                                        </Label>
                                        <Input
                                            id="notify_date"
                                            name="transfer_date"
                                            type="date"
                                            defaultValue={today()}
                                            max={today()}
                                            required
                                        />
                                        <InputError
                                            message={errors.transfer_date}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>{t('Gönderilen hesap')}</Label>
                                    <Select
                                        value={bank}
                                        onValueChange={setBank}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                {t('Belirtmek istemiyorum')}
                                            </SelectItem>
                                            {notify.banks.map((b) => (
                                                <SelectItem
                                                    key={b.value}
                                                    value={b.value}
                                                >
                                                    {b.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.bank_account_id}
                                    />
                                </div>
                                {notify.orders.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label>
                                            {t('Sipariş (isteğe bağlı)')}
                                        </Label>
                                        <Select
                                            value={order}
                                            onValueChange={setOrder}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    {t('Cari bakiyeme')}
                                                </SelectItem>
                                                {notify.orders.map((o) => (
                                                    <SelectItem
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {o.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.order_id} />
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="notify_sender">
                                        {t('Gönderen (hesap sahibi)')}
                                    </Label>
                                    <Input
                                        id="notify_sender"
                                        name="sender_name"
                                        maxLength={255}
                                    />
                                    <InputError message={errors.sender_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="notify_receipt">
                                        {t(
                                            'Dekont (görsel ya da PDF, en fazla 5 MB)',
                                        )}
                                    </Label>
                                    <Input
                                        id="notify_receipt"
                                        name="receipt"
                                        type="file"
                                        accept="image/*,application/pdf"
                                    />
                                    <InputError message={errors.receipt} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="notify_description">
                                        {t('Açıklama')}
                                    </Label>
                                    <Textarea
                                        id="notify_description"
                                        name="description"
                                        rows={2}
                                        maxLength={500}
                                    />
                                    <InputError message={errors.description} />
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
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <Spinner /> : <Send />}
                                        {t('Bildir')}
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

/** Son havale bildirimleri ve durumları. */
export function PaymentNotifyHistory({
    notify,
}: {
    notify: PaymentNotifyData;
}) {
    if (notify.history.length === 0) {
        return null;
    }

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="border-b px-4 py-3 text-sm font-semibold">
                {t('Havale bildirimlerim')}
            </div>
            <ul className="divide-y">
                {notify.history.map((n) => (
                    <li
                        key={n.id}
                        className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <div className="min-w-0">
                            <div className="font-medium tabular-nums">
                                {n.amount}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {formatDate(n.transfer_date)}
                                {n.order_number && ` · ${n.order_number}`}
                                {n.reject_reason && ` · ${n.reject_reason}`}
                            </div>
                        </div>
                        <Badge
                            variant={
                                n.status === 'approved'
                                    ? 'default'
                                    : n.status === 'rejected'
                                      ? 'destructive'
                                      : 'outline'
                            }
                        >
                            {n.status_label}
                        </Badge>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
