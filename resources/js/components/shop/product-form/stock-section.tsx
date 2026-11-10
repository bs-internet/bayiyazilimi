import { Link } from '@inertiajs/react';
import { History, Warehouse } from 'lucide-react';
import { useState } from 'react';

import { SectionCard } from '@/components/admin/page';
import { CheckField } from '@/components/shop/check-field';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as stockIndex, movements } from '@/routes/panel/shop/stock';
import type { ShopOption } from '@/types';

import { SelectInput, TextInput } from './fields';
import type { ProductFormData, ProductFormProps } from './types';

const TRACKING_OPTIONS: ShopOption[] = [
    { value: 'none', label: t('Takip yok') },
    { value: 'lot', label: t('Lot / parti') },
    { value: 'serial', label: t('Seri numarası') },
];

/** Stok takibi, lot / seri, sipariş adet sınırları ve depo özeti. */
export function StockSection({
    product: p,
    defaults,
    units,
    errors,
}: {
    product: ProductFormData | null;
    defaults: ProductFormProps['defaults'];
    units: ShopOption[];
    errors: Record<string, string>;
}) {
    const [trackStock, setTrackStock] = useState(p?.track_stock ?? true);

    return (
        <SectionCard
            title={t('Stok')}
            action={
                p ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={movements({
                                query: {
                                    product: p.id,
                                },
                            })}
                        >
                            <History data-icon="inline-start" />
                            {t('Hareketler')}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <FieldGroup>
                <CheckField
                    variant="switch"
                    name="track_stock"
                    label={t('Stok takibi yap')}
                    description={t('Kapalıysa stok sınırı uygulanmaz.')}
                    defaultChecked={p?.track_stock ?? true}
                    onCheckedChange={setTrackStock}
                />
                <div
                    className={cn(
                        'flex flex-col gap-4',
                        !trackStock && 'opacity-60',
                    )}
                >
                    <CheckField
                        name="allow_backorder"
                        label={t('Stok yokken satışa izin ver (eksi stok)')}
                        defaultChecked={p?.allow_backorder ?? false}
                    />
                    <TextInput
                        name="min_stock"
                        label={t('Kritik stok')}
                        type="number"
                        min={0}
                        className="sm:max-w-xs"
                        defaultValue={p?.min_stock}
                        placeholder={t('Genel eşik (:low_stock_threshold)', {
                            low_stock_threshold: defaults.low_stock_threshold,
                        })}
                        error={errors.min_stock}
                        description={t(
                            "Stok bu değere inince uyarı verilir. Boşsa Stok Ayarları'ndaki genel eşik.",
                        )}
                    />
                    {/* Özellik kapalıyken alanlar gizlenir, ürünün kayıtlı ayarı korunur. */}
                    {!defaults.traceability && (
                        <>
                            <input
                                type="hidden"
                                name="tracking"
                                value={p?.tracking ?? 'none'}
                            />
                            {p?.expiry_tracking && (
                                <input
                                    type="hidden"
                                    name="expiry_tracking"
                                    value="1"
                                />
                            )}
                        </>
                    )}
                    <div
                        className={cn(
                            'grid gap-4 sm:grid-cols-2',
                            !defaults.traceability && 'hidden',
                        )}
                    >
                        <SelectInput
                            name={
                                defaults.traceability
                                    ? 'tracking'
                                    : 'tracking_disabled'
                            }
                            label={t('İzlenebilirlik')}
                            options={TRACKING_OPTIONS}
                            defaultValue={p?.tracking ?? 'none'}
                            error={errors.tracking}
                            description={
                                p?.has_lots
                                    ? t(
                                          'Kayıtlı lot / seri numaraları var; değiştirirseniz mevcut kayıtlar korunur.',
                                      )
                                    : t(
                                          "Lot ya da seri takipli üründe giriş lot / seri no ile yapılır, siparişte SKT'si yakın olan önce çıkar.",
                                      )
                            }
                        />
                        <div className="pt-6">
                            <CheckField
                                name={
                                    defaults.traceability
                                        ? 'expiry_tracking'
                                        : 'expiry_tracking_disabled'
                                }
                                label={t('Son kullanma tarihi (SKT) takibi')}
                                description={t(
                                    'Girişte SKT zorunlu olur; süresi geçen lot satılmaz.',
                                )}
                                defaultChecked={p?.expiry_tracking ?? false}
                            />
                        </div>
                    </div>
                    <StockSummary product={p} />
                </div>
                <Separator />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <TextInput
                        name="min_qty"
                        label={t('Min. adet')}
                        type="number"
                        min={1}
                        required
                        defaultValue={p?.min_qty ?? 1}
                        error={errors.min_qty}
                    />
                    <TextInput
                        name="max_qty"
                        label={t('Maks. adet')}
                        type="number"
                        min={1}
                        defaultValue={p?.max_qty}
                        error={errors.max_qty}
                        placeholder={t('Sınırsız')}
                    />
                    <SelectInput
                        name="unit"
                        label={t('Birim')}
                        required
                        options={units}
                        defaultValue={p?.unit ?? defaults.unit}
                        error={errors.unit}
                        description={t('Tanımlamalar → Birimler')}
                    />
                    <TextInput
                        name="delivery_days"
                        label={t('Teslim süresi')}
                        type="number"
                        min={0}
                        required
                        suffix={t('gün')}
                        defaultValue={p?.delivery_days ?? 0}
                        error={errors.delivery_days}
                    />
                </div>
            </FieldGroup>
        </SectionCard>
    );
}

function StockSummary({ product }: { product: ProductFormData | null }) {
    if (!product) {
        return (
            <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                {t(
                    'Yeni ürün 0 stokla oluşturulur; stok girişini Stok ekranından yapın.',
                )}
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-2.5">
                <div className="text-sm">
                    <span className="text-muted-foreground">
                        {t('Satılabilir stok:')}{' '}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {product.stock} {product.unit}
                    </span>
                </div>
                <Button variant="link" size="sm" className="h-auto p-0" asChild>
                    <Link href={stockIndex({ query: { q: product.sku } })}>
                        <Warehouse data-icon="inline-start" />
                        {t('Stok ekranında yönet')}
                    </Link>
                </Button>
            </div>
            <ul className="divide-y text-sm">
                {product.stocks.map((s) => (
                    <li
                        key={s.warehouse_id}
                        className="flex items-center justify-between gap-3 px-4 py-2"
                    >
                        <span>
                            {s.warehouse}
                            {!s.is_sellable && (
                                <span className="ml-1 text-xs text-muted-foreground">
                                    {t('(satışa kapalı)')}
                                </span>
                            )}
                        </span>
                        <span className="tabular-nums">{s.quantity}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
