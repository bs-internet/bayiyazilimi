import { Plus } from 'lucide-react';
import { Fragment } from 'react';

import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { t } from '@/lib/i18n';
import { RuleRow } from './rule-row';
import type { SectionProps } from './types';
import { newRule } from './types';

/** "Hangi ürünlere" koşulları. */
export function ProductSection(props: SectionProps) {
    const { data, setData, errors, productDefs, products, rememberProducts } =
        props;

    return (
        <SectionCard
            title={t('Hangi ürünlere')}
            description={t(
                'İndirimin uygulanacağı sepet ürünleri. Ücretsiz kargo ve hediyede: sepette bu ürünlerden en az biri olmalı.',
            )}
        >
            <div className="flex flex-col gap-4">
                <RadioGroup
                    value={data.product_scope}
                    onValueChange={(v) =>
                        setData('product_scope', v as 'all' | 'match')
                    }
                    className="flex flex-wrap gap-6"
                >
                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <RadioGroupItem value="all" />
                        {t('Tüm ürünler')}
                    </label>
                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <RadioGroupItem value="match" />
                        {t('Koşula uyan ürünler')}
                    </label>
                </RadioGroup>

                {data.product_scope === 'match' ? (
                    <div className="flex flex-col gap-2">
                        {data.product_rules.map((rule, r) => (
                            <Fragment key={r}>
                                {r > 0 ? (
                                    <span className="pl-3 text-xs font-medium text-muted-foreground">
                                        {t('ve')}
                                    </span>
                                ) : null}
                                <RuleRow
                                    rule={rule}
                                    defs={productDefs}
                                    errors={errors}
                                    errorKey={`product_rules.${r}`}
                                    products={products}
                                    rememberProducts={rememberProducts}
                                    onChange={(next) =>
                                        setData(
                                            'product_rules',
                                            data.product_rules.map((ru, ri) =>
                                                ri === r ? next : ru,
                                            ),
                                        )
                                    }
                                    onRemove={() =>
                                        setData(
                                            'product_rules',
                                            data.product_rules.filter(
                                                (_, ri) => ri !== r,
                                            ),
                                        )
                                    }
                                />
                            </Fragment>
                        ))}
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData('product_rules', [
                                        ...data.product_rules,
                                        newRule(productDefs[0]),
                                    ])
                                }
                            >
                                <Plus data-icon="inline-start" />
                                {t('Koşul ekle (ve)')}
                            </Button>
                        </div>
                        <InputError message={errors.product_rules} />
                    </div>
                ) : null}
            </div>
        </SectionCard>
    );
}
