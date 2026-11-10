import { Form } from '@inertiajs/react';
import { Repeat, Save } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { changeLot } from '@/routes/panel/shop/orders';

export type OrderPick = {
    warehouse_id: string | null;
    warehouse: string | null;
    location_id: string | null;
    location: string | null;
    lot_id: string | null;
    lot: string | null;
    expires_at: string | null;
    quantity: number;
};

export type OrderLotOption = {
    warehouse_id: string;
    lot_id: string;
    label: string;
    quantity: number;
};

const formatDay = (iso: string) => iso.split('-').reverse().join('.');

/**
 * Sipariş kaleminin toplama bilgisi (depo / lokasyon / lot) ve lot takipli
 * üründe toplanan lotu / seri numarasını değiştirme penceresi.
 */
export function OrderPicks({
    orderId,
    productId,
    tracking,
    picks,
    lotOptions,
    canChange,
}: {
    orderId: string;
    productId: string | null;
    tracking: 'none' | 'lot' | 'serial';
    picks: OrderPick[];
    lotOptions: OrderLotOption[];
    canChange: boolean;
}) {
    const [editing, setEditing] = useState<OrderPick | null>(null);
    const tracked = tracking !== 'none';
    const noun = tracking === 'serial' ? t('seri numarası') : 'lot';

    if (picks.length === 0) {
        return null;
    }

    const options = editing
        ? lotOptions
              .filter(
                  (o) =>
                      o.warehouse_id === editing.warehouse_id &&
                      o.lot_id !== editing.lot_id,
              )
              .map((o) => ({
                  value: o.lot_id,
                  label: t(':o_label (stok: :quantity)', {
                      o_label: o.label,
                      quantity: o.quantity,
                  }),
              }))
        : [];

    return (
        <>
            <div className="mt-1 flex flex-wrap gap-1">
                {picks.map((pick, i) => (
                    <Badge
                        key={i}
                        variant="outline"
                        className="gap-1 font-mono text-[11px]"
                        title={pick.warehouse ?? undefined}
                    >
                        {pick.location}
                        {tracked && (
                            <span
                                className={
                                    pick.lot ? undefined : 'text-destructive'
                                }
                            >
                                · {pick.lot ?? t(':noun belirsiz', { noun })}
                                {pick.expires_at &&
                                    t(' (SKT :expires_at)', {
                                        expires_at: formatDay(pick.expires_at),
                                    })}
                            </span>
                        )}{' '}
                        ×{pick.quantity}
                        {tracked && canChange && productId && (
                            <button
                                type="button"
                                className="ml-0.5 cursor-pointer rounded p-0.5 hover:bg-accent"
                                aria-label={t(':noun değiştir', { noun })}
                                title={t(':noun değiştir', { noun })}
                                onClick={() => setEditing(pick)}
                            >
                                <Repeat className="size-3" />
                            </button>
                        )}
                    </Badge>
                ))}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {tracking === 'serial'
                                ? t('Seri numarasını değiştir')
                                : t('Lotu değiştir')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Depoda fiziksel olarak başka bir :noun toplandıysa buradan düzeltin. Eski :noun aynı lokasyona geri eklenir, yenisi aynı depodan düşülür.',
                                { noun },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    {editing && productId && (
                        <Form
                            {...changeLot.form(orderId)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value={productId}
                                    />
                                    <input
                                        type="hidden"
                                        name="warehouse_id"
                                        value={editing.warehouse_id ?? ''}
                                    />
                                    {editing.location_id && (
                                        <input
                                            type="hidden"
                                            name="location_id"
                                            value={editing.location_id}
                                        />
                                    )}
                                    {editing.lot_id && (
                                        <input
                                            type="hidden"
                                            name="from_lot_id"
                                            value={editing.lot_id}
                                        />
                                    )}
                                    <p className="text-sm">
                                        {t('Mevcut:')}{' '}
                                        <span className="font-mono">
                                            {editing.lot ??
                                                t(':noun belirsiz', { noun })}
                                        </span>{' '}
                                        · {editing.warehouse}{' '}
                                        <span className="font-mono">
                                            {editing.location}
                                        </span>{' '}
                                        ·{' '}
                                        {t(':count adet', {
                                            count: editing.quantity,
                                        })}
                                    </p>
                                    <FieldGroup>
                                        <Field
                                            data-invalid={Boolean(
                                                errors.to_lot_id,
                                            )}
                                        >
                                            <FieldLabel>
                                                {t('Yeni')}{' '}
                                                {tracking === 'serial'
                                                    ? t('seri numarası')
                                                    : 'lot'}
                                            </FieldLabel>
                                            <SearchSelect
                                                name="to_lot_id"
                                                options={options}
                                                placeholder={
                                                    options.length
                                                        ? t('Seçin')
                                                        : t(
                                                              'Bu depoda uygun stok yok',
                                                          )
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.to_lot_id}
                                            />
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                errors.quantity,
                                            )}
                                        >
                                            <FieldLabel htmlFor="lot-qty">
                                                {t('Miktar')}
                                            </FieldLabel>
                                            <Input
                                                id="lot-qty"
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                max={editing.quantity}
                                                defaultValue={
                                                    tracking === 'serial'
                                                        ? 1
                                                        : editing.quantity
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.quantity}
                                            />
                                        </Field>
                                    </FieldGroup>
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <Save data-icon="inline-start" />
                                            )}
                                            {t('Kaydet')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
