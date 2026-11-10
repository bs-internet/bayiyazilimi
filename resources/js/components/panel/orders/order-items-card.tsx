import { Link } from '@inertiajs/react';

import { SectionCard, TotalsList } from '@/components/admin/page';
import { OrderPicks } from '@/components/shop/order-picks';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { isPositive } from '@/lib/decimal';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { edit as productEdit } from '@/routes/panel/shop/products';

import type { OrderDetail } from './types';

/** Sipariş kalemleri tablosu + toplamlar. */
export function OrderItemsCard({
    order,
    canChangeLot,
}: {
    order: OrderDetail;
    canChangeLot: boolean;
}) {
    const carrierName = order.carrier ?? order.cargo_company;

    const totalsRows = [
        ...(order.discount_total_formatted
            ? [
                  {
                      label: t('Ara toplam (indirim öncesi)'),
                      value: order.gross_subtotal_formatted,
                  },
                  {
                      label: t('Kampanya indirimi'),
                      value: `−${order.discount_total_formatted}`,
                  },
              ]
            : []),
        { label: t('Ara toplam (KDV hariç)'), value: order.subtotal_formatted },
        { label: t('KDV'), value: order.tax_total_formatted },
        {
            label: carrierName
                ? isPositive(order.total_desi)
                    ? t('Kargo (:carrier, :desi desi)', {
                          carrier: carrierName,
                          desi: formatNumber(order.total_desi),
                      })
                    : t('Kargo (:carrier)', { carrier: carrierName })
                : t('Kargo'),
            value: order.shipping_total_formatted,
        },
        ...(order.payment_adjustment_formatted
            ? [
                  {
                      label: order.payment_adjustment_label ?? t('Ödeme farkı'),
                      value: order.payment_adjustment_formatted,
                  },
              ]
            : []),
    ];

    return (
        <SectionCard title={t('Ürünler')} flush>
            <div className="overflow-x-auto">
                <Table className="[&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5">
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Ürün')}</TableHead>
                            <TableHead className="text-right">
                                {t('Birim fiyat')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('Adet')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('KDV')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('Tutar')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {order.items.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell className="min-w-64">
                                    <div className="flex items-center gap-3">
                                        <div className="size-11 shrink-0 overflow-hidden rounded-md border bg-muted">
                                            {item.image && (
                                                <img
                                                    src={item.image}
                                                    alt=""
                                                    className="size-full object-cover"
                                                    loading="lazy"
                                                />
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            {item.product_id ? (
                                                <Link
                                                    href={productEdit(
                                                        item.product_id,
                                                    )}
                                                    className="line-clamp-2 font-medium whitespace-normal hover:underline"
                                                >
                                                    {item.name}
                                                </Link>
                                            ) : (
                                                <span className="line-clamp-2 font-medium whitespace-normal">
                                                    {item.name}
                                                </span>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {t('SKU:')}{' '}
                                                <span className="font-mono">
                                                    {item.sku ?? '—'}
                                                </span>
                                            </div>
                                            <OrderPicks
                                                orderId={order.id}
                                                productId={item.product_id}
                                                tracking={item.tracking}
                                                picks={item.picks}
                                                lotOptions={item.lot_options}
                                                canChange={canChangeLot}
                                            />
                                            {item.campaign && (
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1 border-primary/30 text-primary"
                                                >
                                                    {item.campaign}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                    {item.unit_price_formatted}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.quantity}
                                </TableCell>
                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                    %{Number(item.tax_rate)}
                                    <div className="text-xs text-muted-foreground">
                                        {item.tax_amount_formatted}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                    <span className="font-medium">
                                        {item.line_total_formatted}
                                    </span>
                                    {item.discount_formatted && (
                                        <div className="text-xs text-primary">
                                            {t('−:amount indirim', {
                                                amount: item.discount_formatted,
                                            })}
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <div className="flex justify-end border-t px-5 py-4">
                <div className="w-full max-w-sm">
                    <TotalsList
                        rows={totalsRows}
                        total={{
                            label: t('Genel toplam'),
                            value: order.grand_total_formatted,
                        }}
                    />
                    {order.currency_code !== 'TRY' && (
                        <p className="mt-3 text-right text-xs text-muted-foreground">
                            {t('Sipariş kuru: 1 :currency = :rate TL', {
                                currency: order.currency_code,
                                rate: Number(
                                    order.exchange_rate,
                                ).toLocaleString('tr-TR', {
                                    maximumFractionDigits: 4,
                                }),
                            })}
                        </p>
                    )}
                </div>
            </div>
        </SectionCard>
    );
}
