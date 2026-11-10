import { X } from 'lucide-react';

import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { ProductPicker } from '@/components/shop/product-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { ProductPick } from '@/types';
import type { Option, ConditionDef, Rule } from './types';
import { newRule } from './types';

/** Çoklu seçim: listeden ekle, etiketlerden çıkar. */
export function MultiValue({
    value,
    options,
    onChange,
    invalid,
}: {
    value: string[];
    options: Option[];
    onChange: (next: string[]) => void;
    invalid?: boolean;
}) {
    const labels = new Map(options.map((o) => [o.value, o.label]));

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <SearchSelect
                value=""
                onChange={(v) =>
                    v && !value.includes(v) && onChange([...value, v])
                }
                options={options.filter((o) => !value.includes(o.value))}
                placeholder={
                    value.length ? t('Bir tane daha ekle…') : t('Seçin')
                }
                searchable
                aria-invalid={invalid}
            />
            {value.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((v) => (
                        <Badge
                            key={v}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {labels.get(v) ?? t('Silinmiş kayıt')}
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(value.filter((x) => x !== v))
                                }
                                className="rounded-sm p-0.5 hover:bg-foreground/10"
                                aria-label={t('Kaldır')}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

/** [Alan] [Mantık] [Değer] satırı. */
export function RuleRow({
    rule,
    defs,
    onChange,
    onRemove,
    errors,
    errorKey,
    products,
    rememberProducts,
}: {
    rule: Rule;
    defs: ConditionDef[];
    onChange: (rule: Rule) => void;
    onRemove: () => void;
    errors: Record<string, string>;
    errorKey: string;
    products: Record<string, ProductPick>;
    rememberProducts: (picked: ProductPick[]) => void;
}) {
    const def = defs.find((d) => d.key === rule.field);
    const valueError = errors[`${errorKey}.value`];
    const operatorError = errors[`${errorKey}.operator`];
    const list = Array.isArray(rule.value) ? rule.value : [];

    return (
        <div className="flex flex-col gap-2 rounded-md border bg-muted/20 p-3">
            <div className="grid gap-2 md:grid-cols-[minmax(0,12rem)_minmax(0,11rem)_minmax(0,1fr)_auto] md:items-start">
                <SearchSelect
                    aria-label={t('Alan')}
                    value={rule.field}
                    onChange={(key) => {
                        const next = defs.find((d) => d.key === key);

                        if (next) {
                            onChange(newRule(next));
                        }
                    }}
                    options={defs.map((d) => ({
                        value: d.key,
                        label: d.label,
                    }))}
                />
                <SearchSelect
                    aria-label={t('Mantık')}
                    value={rule.operator}
                    onChange={(operator) => onChange({ ...rule, operator })}
                    options={def?.operators ?? []}
                    aria-invalid={Boolean(operatorError)}
                />
                <div className="min-w-0">
                    {def?.input === 'number' ? (
                        <div className="flex items-center gap-2">
                            <Input
                                type="number"
                                min={0}
                                step="any"
                                value={String(rule.value ?? '')}
                                onChange={(e) =>
                                    onChange({ ...rule, value: e.target.value })
                                }
                                aria-invalid={Boolean(valueError)}
                                aria-label={t('Değer')}
                            />
                            {def.unit ? (
                                <span className="text-sm text-muted-foreground">
                                    {def.unit}
                                </span>
                            ) : null}
                        </div>
                    ) : def?.input === 'text' ? (
                        <Input
                            value={String(rule.value ?? '')}
                            onChange={(e) =>
                                onChange({ ...rule, value: e.target.value })
                            }
                            aria-invalid={Boolean(valueError)}
                            aria-label={t('Değer')}
                        />
                    ) : def?.input === 'select' ? (
                        <SearchSelect
                            aria-label={t('Değer')}
                            value={String(rule.value ?? '')}
                            onChange={(value) => onChange({ ...rule, value })}
                            options={def.options}
                            aria-invalid={Boolean(valueError)}
                        />
                    ) : def?.input === 'multi' ? (
                        <MultiValue
                            value={list}
                            options={def.options}
                            onChange={(value) => onChange({ ...rule, value })}
                            invalid={Boolean(valueError)}
                        />
                    ) : def?.input === 'products' ? (
                        <span className="flex h-9 items-center text-sm text-muted-foreground">
                            {t(':list_length ürün seçili (aşağıda)', {
                                list_length: list.length,
                            })}
                        </span>
                    ) : null}
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onRemove}
                    aria-label={t('Koşulu kaldır')}
                >
                    <X />
                </Button>
            </div>
            {def?.input === 'products' ? (
                <ProductPicker
                    value={list
                        .map((id) => products[id])
                        .filter((p): p is ProductPick => Boolean(p))}
                    onChange={(picked) => {
                        rememberProducts(picked);
                        onChange({ ...rule, value: picked.map((p) => p.id) });
                    }}
                />
            ) : null}
            <InputError message={valueError ?? operatorError} />
        </div>
    );
}
