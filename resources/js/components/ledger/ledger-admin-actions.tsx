import { Form } from '@inertiajs/react';
import {
    FileSpreadsheet,
    HandCoins,
    Printer,
    ReceiptText,
    Save,
    Undo2,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { OpenOrderPicker } from '@/components/ledger/open-order-picker';
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
import { useCan } from '@/hooks/use-permissions';
import { t } from '@/lib/i18n';
import {
    adjust,
    collect,
    exportMethod,
    print,
    reverse,
} from '@/routes/panel/shop/ledger';
import type { LedgerData, LedgerRow, Option } from '@/types';

const today = () => new Date().toISOString().slice(0, 10);

function DialogButtons({ processing }: { processing: boolean }) {
    return (
        <DialogFooter>
            <DialogClose asChild>
                <Button type="button" variant="outline" disabled={processing}>
                    <X />
                    {t('Vazgeç')}
                </Button>
            </DialogClose>
            <Button type="submit" disabled={processing}>
                {processing ? <Spinner /> : <Save />}
                {t('Kaydet')}
            </Button>
        </DialogFooter>
    );
}

/**
 * Panel cari işlemleri: tahsilat, borç / alacak dekontu (açılış bakiyesi dahil),
 * Excel ve yazdırılabilir ekstre. Yetkiye göre görünür.
 */
export function LedgerAdminActions({
    ledger,
    collectionTypes,
}: {
    ledger: LedgerData;
    collectionTypes: Option<string>[];
}) {
    const canCollect = useCan('panel.shop.ledger.collect');
    const canAdjust = useCan('panel.shop.ledger.adjust');
    const canExport = useCan('panel.shop.ledger.export');
    const canPrint = useCan('panel.shop.ledger.print');
    const [collectOpen, setCollectOpen] = useState(false);
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [type, setType] = useState(collectionTypes[0]?.value ?? 'cash');
    const [direction, setDirection] = useState('debit');
    const instrument = type === 'cheque' || type === 'promissory_note';
    const [opening, setOpening] = useState(false);
    const query = {
        query: {
            from: ledger.filters.from ?? undefined,
            to: ledger.filters.to ?? undefined,
        },
    };

    return (
        <>
            {canCollect && (
                <Button size="sm" onClick={() => setCollectOpen(true)}>
                    <HandCoins />
                    {t('Tahsilat ekle')}
                </Button>
            )}
            {canAdjust && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setAdjustOpen(true)}
                >
                    <ReceiptText />
                    {t('Dekont')}
                </Button>
            )}
            {canExport && (
                <Button size="sm" variant="outline" asChild>
                    <a href={exportMethod.url(ledger.account_id, query)}>
                        <FileSpreadsheet />
                        {t('Excel')}
                    </a>
                </Button>
            )}
            {canPrint && (
                <Button size="sm" variant="outline" asChild>
                    <a
                        href={print.url(ledger.account_id, query)}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <Printer />
                        {t('Yazdır')}
                    </a>
                </Button>
            )}

            <Dialog open={collectOpen} onOpenChange={setCollectOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('Tahsilat ekle')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Tahsilat cariye alacak olarak yazılır ( :currency).',
                                { currency: ledger.currency },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...collect.form(ledger.account_id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCollectOpen(false)}
                        resetOnSuccess
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="type" value={type} />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>{t('Tür')}</Label>
                                        <Select
                                            value={type}
                                            onValueChange={setType}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {collectionTypes.map((t) => (
                                                    <SelectItem
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.type} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="collect_amount">
                                            {t('Tutar (:currency)', {
                                                currency: ledger.currency,
                                            })}
                                        </Label>
                                        <Input
                                            id="collect_amount"
                                            name="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="collect_date">
                                            {t('Tarih')}
                                        </Label>
                                        <Input
                                            id="collect_date"
                                            name="date"
                                            type="date"
                                            defaultValue={today()}
                                            max={today()}
                                        />
                                        <InputError message={errors.date} />
                                    </div>
                                </div>
                                {instrument && (
                                    <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="collect_serial">
                                                {t('Seri no')}
                                            </Label>
                                            <Input
                                                id="collect_serial"
                                                name="serial_no"
                                                maxLength={64}
                                                required
                                            />
                                            <InputError
                                                message={errors.serial_no}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="collect_due">
                                                {t('Vade')}
                                            </Label>
                                            <Input
                                                id="collect_due"
                                                name="due_date"
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                message={errors.due_date}
                                            />
                                        </div>
                                        {type === 'cheque' && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="collect_bank">
                                                        {t('Banka')}
                                                    </Label>
                                                    <Input
                                                        id="collect_bank"
                                                        name="bank_name"
                                                        maxLength={255}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="collect_branch">
                                                        {t('Şube')}
                                                    </Label>
                                                    <Input
                                                        id="collect_branch"
                                                        name="branch"
                                                        maxLength={255}
                                                    />
                                                </div>
                                            </>
                                        )}
                                        <div className="grid gap-2">
                                            <Label htmlFor="collect_drawer">
                                                {type === 'cheque'
                                                    ? t('Keşideci')
                                                    : t('Borçlu')}
                                            </Label>
                                            <Input
                                                id="collect_drawer"
                                                name="drawer"
                                                maxLength={255}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="collect_image">
                                                {t('Görsel (isteğe bağlı)')}
                                            </Label>
                                            <Input
                                                id="collect_image"
                                                name="image"
                                                type="file"
                                                accept="image/*,application/pdf"
                                            />
                                            <InputError
                                                message={errors.image}
                                            />
                                        </div>
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="collect_description">
                                        {t('Açıklama')}
                                    </Label>
                                    <Input
                                        id="collect_description"
                                        name="description"
                                        maxLength={255}
                                        placeholder={t('Ör. dekont no, banka')}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <OpenOrderPicker
                                    orders={ledger.open_orders}
                                    error={errors.order_ids}
                                />
                                <DialogButtons processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={adjustOpen} onOpenChange={setAdjustOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Borç / alacak dekontu')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Elle düzeltme ya da sisteme geçişte açılış bakiyesi. Borç müşterinin borcunu artırır, alacak azaltır.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...adjust.form(ledger.account_id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setAdjustOpen(false)}
                        resetOnSuccess
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="direction"
                                    value={direction}
                                />
                                <input
                                    type="hidden"
                                    name="opening"
                                    value={opening ? '1' : '0'}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>{t('Yön')}</Label>
                                        <Select
                                            value={direction}
                                            onValueChange={setDirection}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="debit">
                                                    {t(
                                                        'Borç (müşteri borçlanır)',
                                                    )}
                                                </SelectItem>
                                                <SelectItem value="credit">
                                                    {t(
                                                        'Alacak (müşteri alacaklanır)',
                                                    )}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.direction}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="adjust_amount">
                                            {t('Tutar (:currency)', {
                                                currency: ledger.currency,
                                            })}
                                        </Label>
                                        <Input
                                            id="adjust_amount"
                                            name="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="adjust_date">
                                            {t('Tarih')}
                                        </Label>
                                        <Input
                                            id="adjust_date"
                                            name="date"
                                            type="date"
                                            defaultValue={today()}
                                        />
                                        <InputError message={errors.date} />
                                    </div>
                                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                        <Checkbox
                                            checked={opening}
                                            onCheckedChange={(v) =>
                                                setOpening(v === true)
                                            }
                                        />
                                        {t('Açılış bakiyesi')}
                                    </label>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="adjust_description">
                                        {t('Açıklama')}
                                    </Label>
                                    <Textarea
                                        id="adjust_description"
                                        name="description"
                                        maxLength={255}
                                        rows={2}
                                        required
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <DialogButtons processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Elle girilen cari kaydın iptali (ters kayıt). */
export function LedgerReverseButton({ row }: { row: LedgerRow }) {
    const canReverse = useCan('panel.shop.ledger.reverse');

    if (!row.reversible || !canReverse) {
        return null;
    }

    return (
        <ConfirmDelete
            action={reverse(row.id)}
            title={t('Kaydı iptal et')}
            description={t(
                'Kayıt silinmez; aynı tutarda ters kayıt yazılır ve bakiye düzelir.',
            )}
            confirmLabel={t('İptal et')}
            confirmIcon={Undo2}
        >
            <Button
                size="icon"
                variant="ghost"
                className="size-8"
                aria-label={t('Kaydı iptal et')}
            >
                <Undo2 />
            </Button>
        </ConfirmDelete>
    );
}
