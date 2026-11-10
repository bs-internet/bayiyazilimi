import { Form, router } from '@inertiajs/react';
import { Loader2, Minus, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { TableCard } from '@/components/admin/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import type { BasketLine } from '@/types';

type FormDef = {
    action: string;
    method: 'post' | 'get' | 'put' | 'patch' | 'delete';
};

/**
 * Satır adedi: − / + ya da elle giriş; kısa bir beklemeden sonra arka planda
 * kaydedilir (sayfa yenilenmez). 0 satırı çıkarır.
 */
function QuantityInput({ line, action }: { line: BasketLine; action: string }) {
    const [value, setValue] = useState(String(line.quantity));
    const [saving, setSaving] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => () => clearTimeout(timer.current ?? undefined), []);

    const save = (raw: string, delay = 500) => {
        clearTimeout(timer.current ?? undefined);

        const qty = Number.parseInt(raw, 10);

        if (!Number.isFinite(qty) || qty < 0 || qty === line.quantity) {
            return;
        }

        timer.current = setTimeout(() => {
            router.post(
                action,
                { quantities: { [line.id]: qty }, auto: 1 },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onStart: () => setSaving(true),
                    onFinish: () => setSaving(false),
                },
            );
        }, delay);
    };

    const change = (next: string, delay?: number) => {
        setValue(next);
        save(next, delay);
    };

    const step = (diff: number) => {
        const current = Number.parseInt(value, 10) || 0;
        const max = line.max_qty ?? Number.POSITIVE_INFINITY;
        change(String(Math.min(max, Math.max(0, current + diff))), 400);
    };

    return (
        <div className="flex w-32 items-center">
            <Button
                type="button"
                variant="outline"
                size="icon-sm"
                className="rounded-r-none"
                onClick={() => step(-1)}
                aria-label={t(':line_name adedini azalt', {
                    line_name: line.name,
                })}
            >
                <Minus />
            </Button>
            <Input
                type="number"
                inputMode="numeric"
                value={value}
                min={0}
                max={line.max_qty ?? undefined}
                onChange={(e) => change(e.target.value)}
                onBlur={() => save(value, 0)}
                aria-label={t(':line_name adedi', { line_name: line.name })}
                className="h-8 [appearance:textfield] rounded-none border-x-0 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none"
            />
            <Button
                type="button"
                variant="outline"
                size="icon-sm"
                className="rounded-l-none"
                onClick={() => step(1)}
                aria-label={t(':line_name adedini artır', {
                    line_name: line.name,
                })}
            >
                {saving ? <Loader2 className="animate-spin" /> : <Plus />}
            </Button>
        </div>
    );
}

/**
 * Bayi paneli sepet / teklif listesi tablosu: ürün, birim fiyat, adet,
 * tutar ve satır silme. Adetler anında kaydedilir.
 */
export function DealerBasketTable({
    lines,
    updateForm,
    removeForm,
    showPrices,
    toolbar,
}: {
    lines: BasketLine[];
    updateForm: FormDef;
    removeForm: (productId: string) => FormDef;
    showPrices: boolean;
    toolbar?: ReactNode;
}) {
    return (
        <>
            <TableCard toolbar={toolbar}>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Ürün')}</TableHead>
                                {showPrices && (
                                    <TableHead className="text-right">
                                        {t('Birim fiyat')}
                                    </TableHead>
                                )}
                                <TableHead className="w-36">
                                    {t('Adet')}
                                </TableHead>
                                {showPrices && (
                                    <TableHead className="text-right">
                                        {t('Tutar')}
                                    </TableHead>
                                )}
                                <TableHead className="w-12">
                                    <span className="sr-only">
                                        {t('İşlem')}
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.map((line) => (
                                <TableRow key={line.key ?? line.id}>
                                    <TableCell>
                                        <div className="flex min-w-60 items-center gap-3">
                                            <img
                                                src={line.image}
                                                alt=""
                                                className="size-12 shrink-0 rounded-md border bg-white object-contain"
                                                loading="lazy"
                                            />
                                            <div className="min-w-0">
                                                <div className="line-clamp-2 font-medium whitespace-normal">
                                                    {line.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {line.brand
                                                        ? `${line.brand} · `
                                                        : ''}
                                                    {t('SKU: :sku', {
                                                        sku: line.sku,
                                                    })}
                                                </div>
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {line.gift && (
                                                        <Badge className="bg-emerald-600 text-white">
                                                            {t('Hediye')}
                                                            {line.campaign
                                                                ? ` · ${line.campaign}`
                                                                : ''}
                                                        </Badge>
                                                    )}
                                                    {!line.gift &&
                                                        line.campaign &&
                                                        line.discount_formatted && (
                                                            <Badge className="bg-primary/10 text-primary">
                                                                {line.campaign}{' '}
                                                                −
                                                                {
                                                                    line.discount_formatted
                                                                }
                                                            </Badge>
                                                        )}
                                                    {showPrices &&
                                                        !line.purchasable && (
                                                            <Badge variant="destructive">
                                                                {t(
                                                                    'Yalnızca teklifle satılır',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    {showPrices &&
                                                        line.purchasable &&
                                                        !line.in_stock && (
                                                            <Badge variant="destructive">
                                                                {t(
                                                                    'Yetersiz stok (mevcut: :stock)',
                                                                    {
                                                                        stock: line.stock,
                                                                    },
                                                                )}
                                                            </Badge>
                                                        )}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    {showPrices && (
                                        <TableCell className="text-right tabular-nums">
                                            {line.has_price
                                                ? line.unit_gross_formatted
                                                : '—'}
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        {line.gift ? (
                                            <span className="block w-24 text-center tabular-nums">
                                                {line.quantity}
                                            </span>
                                        ) : (
                                            <QuantityInput
                                                // Sunucu adedi düzeltirse kutu yeniden kurulur.
                                                key={`${line.id}-${line.quantity}`}
                                                line={line}
                                                action={updateForm.action}
                                            />
                                        )}
                                    </TableCell>
                                    {showPrices && (
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {line.gift
                                                ? t('Ücretsiz')
                                                : line.has_price
                                                  ? line.line_gross_formatted
                                                  : '—'}
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        {line.gift ? null : (
                                            <Button
                                                type="submit"
                                                form={`dealer-basket-remove-${line.id}`}
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':line_name ürününü çıkar',
                                                    { line_name: line.name },
                                                )}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </TableCard>
            {/* Satır silme formları güncelleme formunun dışında (iç içe form olamaz). */}
            {lines
                .filter((line) => !line.gift)
                .map((line) => (
                    <Form
                        key={line.id}
                        {...removeForm(line.id)}
                        options={{ preserveScroll: true }}
                        id={`dealer-basket-remove-${line.id}`}
                        className="hidden"
                    >
                        {null}
                    </Form>
                ))}
        </>
    );
}
