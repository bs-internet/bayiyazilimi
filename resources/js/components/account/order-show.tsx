import { Form, Link } from '@inertiajs/react';
import { RotateCcw, Undo2 } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import type { SnapshotAddress } from '@/components/account/parts';
import {
    flushTableClass,
    SnapshotAddressBlock,
} from '@/components/account/parts';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    InfoList,
    MetaDot,
    SectionCard,
    TotalsList,
} from '@/components/admin/page';
import type { BankAccountInfo } from '@/components/shop/bank-account-list';
import { BankAccountList } from '@/components/shop/bank-account-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { isPositive } from '@/lib/decimal';
import { formatDateTime, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';

export type OrderShowProps = {
    /** Havale / EFT ödemesi bekleyen siparişte gösterilecek banka hesapları. */
    bankAccounts?: BankAccountInfo[] | null;
    order: {
        id: string;
        number: string;
        status: string;
        status_label: string;
        payment_status: string;
        payment_status_label: string;
        payment_method_label: string;
        /** Vadeli siparişte vade tarihi. */
        due_date?: string | null;
        billing_address: SnapshotAddress | null;
        shipping_address: SnapshotAddress | null;
        customer_note: string | null;
        cargo_company: string | null;
        tracking_number: string | null;
        tracking_url: string | null;
        total_desi: string;
        subtotal: string;
        /** Kampanya indirimi varsa indirim öncesi ara toplam. */
        gross_subtotal?: string | null;
        discount_total?: string | null;
        discounts?: { label: string; amount: string }[];
        tax_total: string;
        shipping_total: string;
        /** Ödeme yöntemi farkı (havale indirimi −). */
        payment_adjustment?: string | null;
        payment_adjustment_label?: string | null;
        grand_total: string;
        created_at: string | null;
        public_url: string | null;
        can_return: boolean;
        items: {
            id: string;
            name: string;
            sku: string | null;
            quantity: number;
            unit_price: string;
            line_total: string;
            campaign?: string | null;
            discount?: string | null;
        }[];
        histories: {
            id: string;
            status_label: string | null;
            note: string | null;
            created_at: string | null;
        }[];
    };
};

export function OrderShowContent({
    area,
    order,
    bankAccounts,
}: OrderShowProps & { area: AccountArea }) {
    const routes = accountRoutes(area);
    const discounts = order.discounts ?? [];
    const hasDiscount = Boolean(order.discount_total);

    const totalRows = [
        ...(hasDiscount && order.gross_subtotal
            ? [
                  { label: t('Ürünler toplamı'), value: order.gross_subtotal },
                  ...(discounts.length > 0
                      ? discounts.map((d) => ({
                            label: d.label,
                            value: (
                                <span className="text-emerald-600 dark:text-emerald-400">
                                    −{d.amount}
                                </span>
                            ),
                        }))
                      : [
                            {
                                label: t('Kampanya indirimi'),
                                value: (
                                    <span className="text-emerald-600 dark:text-emerald-400">
                                        −{order.discount_total}
                                    </span>
                                ),
                            },
                        ]),
              ]
            : []),
        { label: t('Ara toplam (KDV hariç)'), value: order.subtotal },
        { label: t('KDV'), value: order.tax_total },
        { label: t('Kargo'), value: order.shipping_total },
        ...(order.payment_adjustment
            ? [
                  {
                      label:
                          order.payment_adjustment_label ?? t('Ödeme indirimi'),
                      value: order.payment_adjustment,
                  },
              ]
            : []),
    ];

    return (
        <>
            <AccountTitle
                area={area}
                title={t('Sipariş :number', { number: order.number })}
                parent={{
                    title: t('Siparişlerim'),
                    href: routes.orders.index(),
                }}
                badges={
                    <>
                        <OrderStatusBadge
                            status={order.status}
                            label={order.status_label}
                        />
                        <PaymentStatusBadge
                            status={order.payment_status}
                            label={order.payment_status_label}
                        />
                    </>
                }
                meta={
                    <>
                        <span>{formatDateTime(order.created_at)}</span>
                        <MetaDot />
                        <span>
                            {t(':count kalem', { count: order.items.length })}
                        </span>
                        <MetaDot />
                        <span>
                            {t('Toplam :grand_total', {
                                grand_total: order.grand_total,
                            })}
                        </span>
                    </>
                }
                actions={
                    <>
                        <Form
                            {...routes.orders.reorder.form(order.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <RotateCcw data-icon="inline-start" />
                                    {t('Tekrar sipariş ver')}
                                </Button>
                            )}
                        </Form>
                        {order.can_return ? (
                            <Button asChild>
                                <Link href={routes.returns.create(order.id)}>
                                    <Undo2 data-icon="inline-start" />
                                    {t('İade talebi oluştur')}
                                </Link>
                            </Button>
                        ) : null}
                    </>
                }
            />

            <EntityLayout>
                <EntityMain>
                    {bankAccounts?.length ? (
                        <SectionCard
                            title={t('Havale / EFT bilgileri')}
                            description={t(
                                'Ödemeyi aşağıdaki hesaplardan birine yapın; açıklamaya sipariş numaranızı (:number) yazın.',
                                { number: order.number },
                            )}
                        >
                            <BankAccountList accounts={bankAccounts} />
                        </SectionCard>
                    ) : null}
                    <SectionCard
                        title={t('Ürünler')}
                        flush
                        footer={
                            <div className="ml-auto w-full max-w-sm">
                                <TotalsList
                                    rows={totalRows}
                                    total={{
                                        label: t('Genel toplam'),
                                        value: order.grand_total,
                                    }}
                                />
                            </div>
                        }
                    >
                        <div className={flushTableClass}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Ürün')}</TableHead>
                                        <TableHead className="text-center">
                                            {t('Adet')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Birim (KDV hariç)')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Tutar (KDV dahil)')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {item.name}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                    {item.sku && (
                                                        <span>
                                                            {t('Kod: :sku', {
                                                                sku: item.sku,
                                                            })}
                                                        </span>
                                                    )}
                                                    {item.discount && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-transparent bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                                        >
                                                            {item.campaign ??
                                                                t(
                                                                    'Kampanya',
                                                                )}{' '}
                                                            · −{item.discount}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {item.quantity}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.unit_price}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {item.line_total}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </SectionCard>

                    {order.histories.length > 0 ? (
                        <SectionCard title={t('Sipariş geçmişi')}>
                            <ol className="flex flex-col gap-4 border-l pl-5 text-sm">
                                {order.histories.map((h) => (
                                    <li key={h.id} className="relative">
                                        <span
                                            aria-hidden
                                            className="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full border-2 border-background bg-primary"
                                        />
                                        <div className="font-medium">
                                            {h.status_label ?? t('Not')}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDateTime(h.created_at)}
                                        </div>
                                        {h.note ? (
                                            <p className="mt-1">{h.note}</p>
                                        ) : null}
                                    </li>
                                ))}
                            </ol>
                        </SectionCard>
                    ) : null}
                </EntityMain>

                <EntitySidebar>
                    <SectionCard title={t('Ödeme')}>
                        <InfoList
                            items={[
                                {
                                    label: t('Yöntem'),
                                    value: order.payment_method_label,
                                },
                                {
                                    label: t('Durum'),
                                    value: order.payment_status_label,
                                },
                                ...(order.due_date
                                    ? [
                                          {
                                              label: t('Vade'),
                                              value: new Date(
                                                  order.due_date,
                                              ).toLocaleDateString('tr-TR'),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </SectionCard>

                    {order.cargo_company || order.tracking_number ? (
                        <SectionCard title={t('Kargo')}>
                            <InfoList
                                items={[
                                    {
                                        label: t('Firma'),
                                        value: order.cargo_company ?? '—',
                                    },
                                    ...(order.tracking_number
                                        ? [
                                              {
                                                  label: t('Takip no'),
                                                  value: order.tracking_url ? (
                                                      <a
                                                          href={
                                                              order.tracking_url
                                                          }
                                                          target="_blank"
                                                          rel="noreferrer"
                                                          className="text-primary underline underline-offset-2"
                                                      >
                                                          {
                                                              order.tracking_number
                                                          }
                                                      </a>
                                                  ) : (
                                                      order.tracking_number
                                                  ),
                                              },
                                          ]
                                        : []),
                                    ...(isPositive(order.total_desi)
                                        ? [
                                              {
                                                  label: t('Desi'),
                                                  value: formatNumber(
                                                      order.total_desi,
                                                  ),
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        </SectionCard>
                    ) : null}

                    {order.shipping_address ? (
                        <SectionCard title={t('Teslimat adresi')}>
                            <SnapshotAddressBlock
                                address={order.shipping_address}
                            />
                        </SectionCard>
                    ) : null}

                    {order.billing_address ? (
                        <SectionCard title={t('Fatura adresi')}>
                            <SnapshotAddressBlock
                                address={order.billing_address}
                            />
                        </SectionCard>
                    ) : null}

                    {order.customer_note ? (
                        <SectionCard title={t('Sipariş notunuz')}>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {order.customer_note}
                            </p>
                        </SectionCard>
                    ) : null}
                </EntitySidebar>
            </EntityLayout>
        </>
    );
}
