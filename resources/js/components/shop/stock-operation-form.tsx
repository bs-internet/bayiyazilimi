import { Form } from '@inertiajs/react';
import { ArrowLeftRight, Save } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { t } from '@/lib/i18n';
import { adjust, relocate, transfer } from '@/routes/panel/shop/stock';
import type { ShopOption } from '@/types';

export type StockWarehouseOption = { id: string; name: string };

export type StockLocationOption = {
    id: string;
    warehouse_id: string;
    code: string;
};

/** Ürünün bir lokasyondaki (lot takipliyse o lottaki) miktarı. */
export type StockLocationQuantity = {
    location_id: string;
    warehouse_id?: string;
    code: string | null;
    lot_id?: string | null;
    lot?: string | null;
    expires_at?: string | null;
    expired?: boolean;
    quantity: number;
};

export type StockOperationRow = {
    id: string;
    quantities: Record<string, number>;
    locations: StockLocationQuantity[];
    tracking?: 'none' | 'lot' | 'serial';
    expiry_tracking?: boolean;
};

const OPERATIONS: ShopOption[] = [
    { value: 'in', label: t('Stok girişi (+)') },
    { value: 'out', label: t('Stok çıkışı (−)') },
    { value: 'count', label: t('Sayım (miktarı eşitle)') },
    { value: 'relocate', label: t('Lokasyon değiştir (depo içi)') },
    { value: 'transfer', label: t('Depolar arası transfer') },
];

const formatDay = (iso: string) => iso.split('-').reverse().join('.');

/**
 * Stok işlemi formu (giriş, çıkış, sayım, depo içi lokasyon değişimi, depolar
 * arası transfer). Lokasyon / lot boş bırakılırsa otomatik seçilir. Lot / seri
 * takipli üründe girişte lot (seri) no yazılır; sayım ve lokasyon değişiminde
 * lot seçilir. Çağıran taraf her açılışta yeniden kurar (key); başarılı
 * kayıttan sonra `onDone` çağrılır.
 */
export function StockOperationForm({
    row,
    warehouses,
    locations,
    defaultWarehouseId,
    defaultOperation = 'in',
    onDone,
}: {
    row: StockOperationRow;
    warehouses: StockWarehouseOption[];
    locations: StockLocationOption[];
    defaultWarehouseId?: string;
    defaultOperation?: string;
    onDone: () => void;
}) {
    const [operation, setOperation] = useState(defaultOperation);
    const [warehouseId, setWarehouseId] = useState(
        defaultWarehouseId ?? warehouses[0]?.id ?? '',
    );
    const [toWarehouseId, setToWarehouseId] = useState(
        warehouses.find((w) => w.id !== warehouseId)?.id ?? '',
    );
    const [lotId, setLotId] = useState('');

    const tracking = row.tracking ?? 'none';
    const tracked = tracking !== 'none';
    const serial = tracking === 'serial';
    const lotNoun = serial ? t('Seri no') : t('Lot');

    const warehouseOptions = warehouses.map((w) => ({
        value: w.id,
        label: t(':name (mevcut: :quantity)', {
            name: w.name,
            quantity: row.quantities[w.id] ?? 0,
        }),
    }));

    // Lot seçiliyse yalnız o lottaki miktar.
    const qtyAt = (locationId: string) =>
        row.locations
            .filter(
                (l) =>
                    l.location_id === locationId &&
                    (!tracked || !lotId || l.lot_id === lotId),
            )
            .reduce((sum, l) => sum + l.quantity, 0);

    const locationOptions = (whId: string, withQty = true) =>
        locations
            .filter((l) => l.warehouse_id === whId)
            .map((l) => ({
                value: l.id,
                label: withQty
                    ? t(':code (mevcut: :id)', {
                          code: l.code,
                          id: qtyAt(l.id),
                      })
                    : l.code,
            }));

    // Kaynak lokasyon: yalnız ürünün (seçili lotun) stoğu olan lokasyonlar.
    const sourceOptions = locationOptions(warehouseId).filter(
        (o) => qtyAt(o.value) > 0,
    );

    // Seçilen depodaki lotlar (lokasyonlar toplanır).
    const lotOptions = Object.values(
        row.locations
            .filter((l) => l.warehouse_id === warehouseId && l.lot_id)
            .reduce<
                Record<string, { value: string; label: string; qty: number }>
            >((acc, l) => {
                const key = l.lot_id as string;
                const label = `${l.lot}${l.expires_at ? t(' · SKT :expires_at', { expires_at: formatDay(l.expires_at) }) : ''}${l.expired ? t(' (süresi geçmiş)') : ''}`;
                acc[key] = {
                    value: key,
                    label,
                    qty: (acc[key]?.qty ?? 0) + l.quantity,
                };

                return acc;
            }, {}),
    ).map((o) => ({
        value: o.value,
        label: t(':o_label (mevcut: :qty)', { o_label: o.label, qty: o.qty }),
    }));

    const isTransfer = operation === 'transfer';
    const isRelocate = operation === 'relocate';
    const lotRequired = operation === 'count' || isRelocate;
    const formProps = isTransfer
        ? transfer.form()
        : isRelocate
          ? relocate.form()
          : adjust.form();

    return (
        <Form
            {...formProps}
            options={{ preserveScroll: true }}
            resetOnSuccess
            onSuccess={onDone}
            className="flex flex-col gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="product_id" value={row.id} />
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="stock-operation">
                                {t('İşlem türü')}
                            </FieldLabel>
                            <NativeSelect
                                id="stock-operation"
                                name={
                                    isTransfer || isRelocate
                                        ? undefined
                                        : 'operation'
                                }
                                value={operation}
                                onChange={(e) => setOperation(e.target.value)}
                                className="w-full"
                            >
                                {OPERATIONS.map((o) => (
                                    <NativeSelectOption
                                        key={o.value}
                                        value={o.value}
                                    >
                                        {o.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </Field>

                        <Field
                            data-invalid={Boolean(
                                errors.warehouse_id || errors.from_warehouse_id,
                            )}
                        >
                            <FieldLabel>
                                {isTransfer ? t('Kaynak depo') : t('Depo')}
                            </FieldLabel>
                            <SearchSelect
                                name={
                                    isRelocate
                                        ? undefined
                                        : isTransfer
                                          ? 'from_warehouse_id'
                                          : 'warehouse_id'
                                }
                                value={warehouseId}
                                onChange={(v) => {
                                    setWarehouseId(v);
                                    setLotId('');
                                }}
                                options={warehouseOptions}
                                required
                            />
                            <InputError
                                message={
                                    errors.warehouse_id ??
                                    errors.from_warehouse_id
                                }
                            />
                        </Field>

                        {tracked && operation === 'in' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    name="lot_code"
                                    label={
                                        serial
                                            ? t('Seri numarası')
                                            : t('Lot no')
                                    }
                                    required
                                    error={errors.lot_code}
                                />
                                <TextField
                                    name="expires_at"
                                    label={t('Son kullanma tarihi')}
                                    type="date"
                                    required={row.expiry_tracking}
                                    error={errors.expires_at}
                                    description={
                                        row.expiry_tracking
                                            ? undefined
                                            : t('İsteğe bağlı')
                                    }
                                />
                            </div>
                        )}

                        {tracked && operation !== 'in' && (
                            <Field data-invalid={Boolean(errors.lot_id)}>
                                <FieldLabel>{lotNoun}</FieldLabel>
                                <SearchSelect
                                    key={`lot-${warehouseId}`}
                                    name="lot_id"
                                    value={lotId}
                                    onChange={setLotId}
                                    options={lotOptions}
                                    placeholder={
                                        lotRequired
                                            ? t('Seçin')
                                            : t("Otomatik (SKT'si yakın önce)")
                                    }
                                    required={lotRequired}
                                    clearable={!lotRequired}
                                />
                                {!lotRequired && (
                                    <FieldDescription>
                                        {t(
                                            "Boşsa süresi geçmemiş, SKT'si en yakın",
                                        )}{' '}
                                        {serial ? 'seri' : 'lot'}{' '}
                                        {t('önce düşülür.')}
                                    </FieldDescription>
                                )}
                                <InputError message={errors.lot_id} />
                            </Field>
                        )}

                        {isRelocate ? (
                            <>
                                <Field
                                    data-invalid={Boolean(
                                        errors.from_location_id,
                                    )}
                                >
                                    <FieldLabel>
                                        {t('Kaynak lokasyon')}
                                    </FieldLabel>
                                    <SearchSelect
                                        key={`from-${warehouseId}-${lotId}`}
                                        name="from_location_id"
                                        options={sourceOptions}
                                        placeholder={
                                            sourceOptions.length
                                                ? t('Seçin')
                                                : t('Bu depoda stok yok')
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.from_location_id}
                                    />
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        errors.to_location_id,
                                    )}
                                >
                                    <FieldLabel>
                                        {t('Hedef lokasyon')}
                                    </FieldLabel>
                                    <SearchSelect
                                        key={`to-${warehouseId}`}
                                        name="to_location_id"
                                        options={locationOptions(warehouseId)}
                                        placeholder={t('Seçin')}
                                        required
                                    />
                                    <InputError
                                        message={errors.to_location_id}
                                    />
                                </Field>
                            </>
                        ) : isTransfer ? (
                            <>
                                <Field
                                    data-invalid={Boolean(
                                        errors.from_location_id,
                                    )}
                                >
                                    <FieldLabel>
                                        {t('Kaynak lokasyon')}
                                    </FieldLabel>
                                    <SearchSelect
                                        key={`fw-${warehouseId}-${lotId}`}
                                        name="from_location_id"
                                        options={sourceOptions}
                                        placeholder={t('Otomatik')}
                                        clearable
                                    />
                                    <FieldDescription>
                                        {t(
                                            'Boşsa kaynak depodaki lokasyonlardan toplama sırasıyla düşülür.',
                                        )}
                                    </FieldDescription>
                                    <InputError
                                        message={errors.from_location_id}
                                    />
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        errors.to_warehouse_id,
                                    )}
                                >
                                    <FieldLabel>{t('Hedef depo')}</FieldLabel>
                                    <SearchSelect
                                        name="to_warehouse_id"
                                        value={toWarehouseId}
                                        onChange={setToWarehouseId}
                                        options={warehouseOptions}
                                        required
                                    />
                                    <InputError
                                        message={errors.to_warehouse_id}
                                    />
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        errors.to_location_id,
                                    )}
                                >
                                    <FieldLabel>
                                        {t('Hedef lokasyon')}
                                    </FieldLabel>
                                    <SearchSelect
                                        key={`tw-${toWarehouseId}`}
                                        name="to_location_id"
                                        options={locationOptions(
                                            toWarehouseId,
                                            false,
                                        )}
                                        placeholder={t('Otomatik')}
                                        clearable
                                    />
                                    <FieldDescription>
                                        {t(
                                            'Boşsa ürünün hedef depodaki ana lokasyonuna (yoksa Atanmamış) girer.',
                                        )}
                                        {tracked &&
                                            t(
                                                ' Lot / seri numaraları aynen taşınır.',
                                            )}
                                    </FieldDescription>
                                    <InputError
                                        message={errors.to_location_id}
                                    />
                                </Field>
                            </>
                        ) : (
                            <Field data-invalid={Boolean(errors.location_id)}>
                                <FieldLabel>{t('Lokasyon')}</FieldLabel>
                                <SearchSelect
                                    key={`loc-${warehouseId}-${lotId}`}
                                    name="location_id"
                                    options={locationOptions(warehouseId)}
                                    placeholder={t('Otomatik')}
                                    clearable
                                />
                                <FieldDescription>
                                    {operation === 'in'
                                        ? t(
                                              'Boşsa ürünün bu depodaki ana lokasyonuna (yoksa Atanmamış) girer.',
                                          )
                                        : operation === 'out'
                                          ? t(
                                                'Boşsa toplama sırasıyla stoğu olan lokasyonlardan düşer.',
                                            )
                                          : t(
                                                'Seçilirse yalnız o lokasyonun miktarı eşitlenir; boşsa depo toplamı.',
                                            )}
                                </FieldDescription>
                                <InputError message={errors.location_id} />
                            </Field>
                        )}

                        <TextField
                            name="quantity"
                            label={
                                operation === 'count'
                                    ? t('Sayılan miktar')
                                    : t('Miktar')
                            }
                            type="number"
                            min={operation === 'count' ? 0 : 1}
                            required
                            error={errors.quantity}
                            description={
                                serial && operation === 'in'
                                    ? t(
                                          'Seri numaralı üründe tek seferde 1 adet; çoklu giriş için Mal Kabul.',
                                      )
                                    : undefined
                            }
                        />
                        <TextField
                            name="note"
                            label={t('Not')}
                            placeholder={t('Ör. irsaliye no, sayım tarihi')}
                            error={errors.note}
                        />
                    </FieldGroup>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {isTransfer || isRelocate ? (
                                <ArrowLeftRight data-icon="inline-start" />
                            ) : (
                                <Save data-icon="inline-start" />
                            )}
                            {t('Kaydet')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
