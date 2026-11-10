import { PaymentStatusBadge } from '@/components/account/status-badge';
import { SectionCard } from '@/components/admin/page';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';

import { t } from '@/lib/i18n';
import { RefundButton } from './refund-button';
import { RefundList } from './refund-list';
import type { OrderDetail } from './types';

/** Siparişin ödeme kayıtları (kart iadesi dahil). */
export function OrderPaymentsCard({
    order,
    canRefund,
}: {
    order: OrderDetail;
    canRefund: boolean;
}) {
    return (
        <SectionCard
            title={t('Ödemeler')}
            description={
                order.paid_at
                    ? t('Ödeme tarihi: :paid_at', {
                          paid_at: formatDateTime(order.paid_at),
                      })
                    : undefined
            }
            flush
        >
            {order.payments.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    {t('Ödeme kaydı yok.')}
                    {order.payment_method === 'bank_transfer' &&
                        t(
                            ' Havale/EFT siparişlerinde ödemeyi aldığınızda "Ödeme" düğmesinden durumu "Ödendi" yapın.',
                        )}
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <Table className="[&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5">
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Yöntem')}</TableHead>
                                <TableHead>{t('Tarih')}</TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                                <TableHead className="text-right">
                                    {t('Tutar')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {order.payments.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {p.method_label}
                                            {p.installment > 1 &&
                                                t(' · :installment taksit', {
                                                    installment: p.installment,
                                                })}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {p.merchant_oid}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap tabular-nums">
                                        {formatDateTime(p.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <PaymentStatusBadge
                                            status={p.status}
                                            label={p.status_label}
                                        />
                                        {p.failed_reason && (
                                            <div className="mt-1 max-w-56 text-xs whitespace-normal text-destructive">
                                                {p.failed_reason}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                                        {p.amount_formatted}
                                        {p.fee_formatted && (
                                            <div className="text-xs font-normal text-muted-foreground">
                                                {t(
                                                    '+:fee_formatted vade farkı',
                                                    {
                                                        fee_formatted:
                                                            p.fee_formatted,
                                                    },
                                                )}
                                            </div>
                                        )}
                                        {p.refunded_formatted && (
                                            <div className="text-xs font-normal text-destructive">
                                                {t(
                                                    'İade: :refunded_formatted',
                                                    {
                                                        refunded_formatted:
                                                            p.refunded_formatted,
                                                    },
                                                )}
                                            </div>
                                        )}
                                        {p.refundable && canRefund && (
                                            <RefundButton payment={p} />
                                        )}
                                        {p.refunds.length > 0 && (
                                            <RefundList
                                                refunds={p.refunds}
                                                canResolve={canRefund}
                                            />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </SectionCard>
    );
}
