import { ShoppingBag } from 'lucide-react';

import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import type { DecimalInput } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import type { ShopOption } from '@/types';

import { LineItemRow } from './line-item-row';
import { fillListPrices, recalc } from './lines';
import type { Line, LineField } from './lines';

/** Teklif kalemleri listesi; satır düzenleme / kaldırma burada. */
export function LineItemsCard({
    items,
    setItems,
    currencyCode,
    editable,
    unpriced,
    units,
    money,
    errors,
}: {
    items: Line[];
    setItems: (items: Line[]) => void;
    currencyCode: string;
    editable: boolean;
    unpriced: number;
    units: ShopOption[];
    money: (value: DecimalInput) => string;
    errors: Record<string, string | undefined>;
}) {
    const updateLine = (productId: string, patch: Partial<Line>) =>
        setItems(
            items.map((line) =>
                line.product_id === productId ? { ...line, ...patch } : line,
            ),
        );

    const changeLine = (productId: string, field: LineField, value: string) =>
        setItems(
            items.map((line) =>
                line.product_id === productId
                    ? recalc(line, field, value)
                    : line,
            ),
        );

    const removeLine = (productId: string) =>
        setItems(items.filter((line) => line.product_id !== productId));

    return (
        <SectionCard
            title={t('Ürünler')}
            description={t(
                "Teklif fiyatlarını :currency_code olarak girin: KDV'siz fiyat, KDV'li fiyat ya da satır toplamından biri yeter, diğerleri hesaplanır. Liste fiyatı talep anındaki site fiyatıdır.",
                { currency_code: currencyCode },
            )}
            action={
                editable && unpriced > 0 ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setItems(fillListPrices(items))}
                    >
                        {t('Boşlara liste fiyatı')}
                    </Button>
                ) : undefined
            }
            flush
        >
            {items.length === 0 ? (
                <Empty className="py-10">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ShoppingBag />
                        </EmptyMedia>
                        <EmptyTitle>{t('Ürün yok')}</EmptyTitle>
                        <EmptyDescription>
                            {t('Yukarıdan ürün arayıp ekleyin.')}
                        </EmptyDescription>
                    </EmptyHeader>
                    <InputError message={errors.items} />
                </Empty>
            ) : (
                <ul className="divide-y">
                    {items.map((line, i) => (
                        <LineItemRow
                            key={line.product_id}
                            line={line}
                            index={i}
                            editable={editable}
                            units={units}
                            money={money}
                            fieldErrors={errors}
                            onChange={changeLine}
                            onUpdate={updateLine}
                            onRemove={removeLine}
                        />
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}
