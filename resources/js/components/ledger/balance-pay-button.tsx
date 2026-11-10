import { Form } from '@inertiajs/react';
import { CreditCard, X } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { OpenOrderPicker } from '@/components/ledger/open-order-picker';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { isPositive, toFixed } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { store } from '@/routes/app/shop/balance-payments';
import type { LedgerOpenOrder } from '@/types';

export type BalancePayData = {
    currency: string;
    suggested: string;
    gateways: { value: string; label: string }[];
    orders: LedgerOpenOrder[];
};

/**
 * Cari tahsilat: bayi bakiyesine kartla ödeme (kart modülü kuruluysa).
 * Ödeme sağlayıcının güvenli (3D) sayfasında yapılır.
 */
export function BalancePayButton({ data }: { data: BalancePayData }) {
    const [open, setOpen] = useState(false);
    const [gateway, setGateway] = useState(data.gateways[0]?.value ?? '');

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <CreditCard data-icon="inline-start" />
                {t('Kartla öde')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Kartla cari ödeme')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Ödeme güvenli ödeme sayfasında (3D Secure) yapılır ve carinize alacak olarak işlenir.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form {...store.form()} className="flex flex-col gap-4">
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="gateway"
                                    value={gateway}
                                />
                                {data.gateways.length > 1 && (
                                    <div className="grid gap-2">
                                        <Label>{t('Ödeme sağlayıcısı')}</Label>
                                        <Select
                                            value={gateway}
                                            onValueChange={setGateway}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {data.gateways.map((g) => (
                                                    <SelectItem
                                                        key={g.value}
                                                        value={g.value}
                                                    >
                                                        {g.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="balance_amount">
                                        {t('Tutar (:currency)', {
                                            currency: data.currency,
                                        })}
                                    </Label>
                                    <Input
                                        id="balance_amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        defaultValue={
                                            isPositive(data.suggested)
                                                ? toFixed(data.suggested)
                                                : ''
                                        }
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                    <InputError message={errors.gateway} />
                                </div>
                                <OpenOrderPicker
                                    orders={data.orders}
                                    error={errors.order_ids}
                                />
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
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <CreditCard />
                                        )}
                                        {t('Ödemeye geç')}
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
