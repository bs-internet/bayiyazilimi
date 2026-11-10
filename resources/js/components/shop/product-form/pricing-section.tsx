import { useState } from 'react';

import { SectionCard } from '@/components/admin/page';
import { FieldGroup } from '@/components/ui/field';
import {
    compare,
    div,
    isPositive,
    sub,
    times,
    toFixed,
    toInput,
    toNumber,
    withRate,
} from '@/lib/decimal';
import { t } from '@/lib/i18n';
import type { ShopOption } from '@/types';

import { SelectInput, TextInput, formatAmount } from './fields';
import type { ProductFormData, ProductFormProps } from './types';

/** Fiyat, para birimi ve KDV; KDV dahil fiyat ve indirim önizlemesi. */
export function PricingSection({
    product: p,
    defaults,
    currencies,
    taxRates,
    errors,
}: {
    product: ProductFormData | null;
    defaults: ProductFormProps['defaults'];
    currencies: ShopOption[];
    taxRates: string[];
    errors: Record<string, string>;
}) {
    const currencyCode = (id: string | null) =>
        currencies.find((c) => c.value === id)?.label.split(' — ')[0] ?? 'TRY';

    const [price, setPrice] = useState(p?.price ?? '0');
    const [comparePrice, setComparePrice] = useState(p?.compare_price ?? '');
    const [taxRate, setTaxRate] = useState(
        toInput(p?.tax_rate ?? defaults.tax_rate, 2).replace(',', '.'),
    );
    const [currency, setCurrency] = useState(
        currencyCode(p?.currency_id ?? defaults.currency_id),
    );

    // Önizleme kesin ondalıkla, sunucudaki Product / Money ile aynı kural.
    const net = toFixed(price);
    const gross = withRate(net, taxRate);
    const discount =
        isPositive(net) && compare(comparePrice, net) > 0
            ? Math.round(
                  toNumber(sub(100, div(times(net, 100, 6), comparePrice, 4))),
              )
            : 0;

    return (
        <SectionCard
            title={t('Fiyatlandırma')}
            description={t(
                'Fiyatlar KDV hariç ve seçilen para biriminde girilir; satışta kura göre çevrilip KDV eklenir.',
            )}
        >
            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <TextInput
                        name="price"
                        label={t('Satış fiyatı (KDV hariç)')}
                        type="number"
                        step="0.0001"
                        min={0}
                        required
                        suffix={currency}
                        defaultValue={p?.price ?? '0'}
                        error={errors.price}
                        onValueChange={setPrice}
                    />
                    <TextInput
                        name="compare_price"
                        label={t('Karşılaştırma fiyatı')}
                        type="number"
                        step="0.0001"
                        min={0}
                        suffix={currency}
                        defaultValue={p?.compare_price}
                        error={errors.compare_price}
                        description={t('Üstü çizili liste fiyatı.')}
                        onValueChange={setComparePrice}
                    />
                    <SelectInput
                        name="currency_id"
                        label={t('Para birimi')}
                        options={currencies}
                        required
                        defaultValue={p?.currency_id ?? defaults.currency_id}
                        error={errors.currency_id}
                        onValueChange={(v) => setCurrency(currencyCode(v))}
                    />
                    <SelectInput
                        name="tax_rate"
                        label={t('KDV oranı')}
                        options={taxRates.map((r) => ({
                            value: r,
                            label: `%${r}`,
                        }))}
                        required
                        defaultValue={taxRate}
                        error={errors.tax_rate}
                        onValueChange={setTaxRate}
                    />
                </div>
                <div className="grid gap-3 rounded-lg border bg-muted/40 p-4 text-sm sm:grid-cols-3">
                    <div>
                        <div className="text-xs text-muted-foreground">
                            {t('KDV hariç')}
                        </div>
                        <div className="font-medium tabular-nums">
                            {formatAmount(net, currency)}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            {t('KDV dahil (%:tax_rate)', { tax_rate: taxRate })}
                        </div>
                        <div className="text-base font-semibold tabular-nums">
                            {formatAmount(gross, currency)}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            {t('İndirim')}
                        </div>
                        <div className="font-medium tabular-nums">
                            {discount > 0 ? (
                                <span className="text-emerald-700 dark:text-emerald-400">
                                    %{discount}
                                </span>
                            ) : (
                                '—'
                            )}
                        </div>
                    </div>
                </div>
            </FieldGroup>
        </SectionCard>
    );
}
