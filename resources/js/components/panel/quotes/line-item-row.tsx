import { X } from 'lucide-react';

import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { DecimalInput } from '@/lib/decimal';
import { toInput } from '@/lib/decimal';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ShopOption } from '@/types';

import { blank } from './lines';
import type { Line, LineField } from './lines';

/** Tek teklif kalemi: ürün bilgisi + miktar / birim / fiyat alanları. */
export function LineItemRow({
    line,
    index: i,
    editable,
    units,
    money,
    fieldErrors,
    onChange: changeLine,
    onUpdate: updateLine,
    onRemove: removeLine,
}: {
    line: Line;
    index: number;
    editable: boolean;
    units: ShopOption[];
    money: (value: DecimalInput) => string;
    fieldErrors: Record<string, string | undefined>;
    onChange: (productId: string, field: LineField, value: string) => void;
    onUpdate: (productId: string, patch: Partial<Line>) => void;
    onRemove: (productId: string) => void;
}) {
    return (
        <li
            className={cn(
                'flex flex-col gap-3 px-4 py-4',
                blank(line.unit_price) && 'bg-chart-3/10',
            )}
        >
            <div className="flex items-start gap-3">
                {line.image ? (
                    <img
                        src={line.image}
                        alt=""
                        className="size-10 shrink-0 rounded border object-contain"
                    />
                ) : null}
                <div className="min-w-0 flex-1">
                    <div className="font-medium">{line.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {line.sku}
                        {line.list_price !== null
                            ? t(' · Liste :list_price + KDV', {
                                  list_price: money(line.list_price),
                              })
                            : ''}
                        {line.stock !== null
                            ? t(' · Stok :stock', { stock: line.stock })
                            : ''}
                        {blank(line.unit_price) ? t(' · Fiyat girilmedi') : ''}
                    </div>
                </div>
                {editable ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t(':line_name kaldır', {
                            line_name: line.name,
                        })}
                        onClick={() => removeLine(line.product_id)}
                    >
                        <X />
                    </Button>
                ) : null}
            </div>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t('Miktar')}
                    </FieldLabel>
                    <Input
                        type="number"
                        min={1}
                        value={line.quantity}
                        disabled={!editable}
                        aria-label={t(':line_name miktar', {
                            line_name: line.name,
                        })}
                        onChange={(e) =>
                            changeLine(
                                line.product_id,
                                'quantity',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={fieldErrors[`items.${i}.quantity`]} />
                </Field>
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t('Birim')}
                    </FieldLabel>
                    <SearchSelect
                        options={
                            line.unit &&
                            !units.some((u) => u.value === line.unit)
                                ? [
                                      ...units,
                                      { value: line.unit, label: line.unit },
                                  ]
                                : units
                        }
                        value={line.unit ?? ''}
                        onChange={(value) =>
                            updateLine(line.product_id, {
                                unit: value || null,
                            })
                        }
                        placeholder={t('Birim')}
                        searchable
                        disabled={!editable}
                    />
                </Field>
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t("KDV'siz fiyat")}
                    </FieldLabel>
                    <Input
                        inputMode="decimal"
                        value={line.unit_price}
                        placeholder={
                            line.list_price !== null
                                ? toInput(line.list_price, 2)
                                : undefined
                        }
                        disabled={!editable}
                        aria-label={t(":line_name KDV'siz birim fiyat", {
                            line_name: line.name,
                        })}
                        onChange={(e) =>
                            changeLine(
                                line.product_id,
                                'unit_price',
                                e.target.value,
                            )
                        }
                    />
                    <InputError
                        message={fieldErrors[`items.${i}.unit_price`]}
                    />
                </Field>
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t('KDV (%)')}
                    </FieldLabel>
                    <Input
                        inputMode="decimal"
                        value={line.tax_rate}
                        disabled={!editable}
                        aria-label={t(':line_name KDV oranı', {
                            line_name: line.name,
                        })}
                        onChange={(e) =>
                            changeLine(
                                line.product_id,
                                'tax_rate',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={fieldErrors[`items.${i}.tax_rate`]} />
                </Field>
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t("KDV'li fiyat")}
                    </FieldLabel>
                    <Input
                        inputMode="decimal"
                        value={line.gross_price}
                        disabled={!editable}
                        aria-label={t(":line_name KDV'li birim fiyat", {
                            line_name: line.name,
                        })}
                        onChange={(e) =>
                            changeLine(
                                line.product_id,
                                'gross_price',
                                e.target.value,
                            )
                        }
                    />
                </Field>
                <Field>
                    <FieldLabel className="text-xs text-muted-foreground">
                        {t('Toplam (KDV dahil)')}
                    </FieldLabel>
                    <Input
                        inputMode="decimal"
                        value={line.total_price}
                        disabled={!editable}
                        aria-label={t(':line_name toplam fiyat', {
                            line_name: line.name,
                        })}
                        className="text-right font-semibold"
                        onChange={(e) =>
                            changeLine(
                                line.product_id,
                                'total_price',
                                e.target.value,
                            )
                        }
                    />
                </Field>
            </div>
        </li>
    );
}
