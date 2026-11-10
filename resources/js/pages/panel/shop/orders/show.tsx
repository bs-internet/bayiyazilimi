import { Head } from '@inertiajs/react';
import { CreditCard, RefreshCw } from 'lucide-react';
import { useState } from 'react';

import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    MetaDot,
    Page,
    PageTitle,
} from '@/components/admin/page';
import { AddressCard } from '@/components/panel/orders/address-card';
import { AdminNoteCard } from '@/components/panel/orders/admin-note-card';
import { InvoiceCard } from '@/components/panel/orders/invoice-card';
import { OrderApprovalAlert } from '@/components/panel/orders/order-approval-alert';
import { OrderContactCard } from '@/components/panel/orders/order-contact-card';
import { OrderCustomerCard } from '@/components/panel/orders/order-customer-card';
import { OrderHistoryCard } from '@/components/panel/orders/order-history-card';
import { OrderItemsCard } from '@/components/panel/orders/order-items-card';
import { OrderPaymentDialog } from '@/components/panel/orders/order-payment-dialog';
import { OrderPaymentsCard } from '@/components/panel/orders/order-payments-card';
import { OrderShippingCard } from '@/components/panel/orders/order-shipping-card';
import { OrderStatusDialog } from '@/components/panel/orders/order-status-dialog';
import type { OrderDetail } from '@/components/panel/orders/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan, useIsSuperAdmin } from '@/hooks/use-permissions';
import { Slot } from '@/lib/extensions';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index } from '@/routes/panel/shop/orders';
import type { ShopOption } from '@/types';

type Props = {
    order: OrderDetail;
    statuses: ShopOption[];
    paymentStatuses: ShopOption[];
    carriers: ShopOption[];
    /** Eklentilerin sipariş verisi (anahtar = eklenti), `orders.show.sidebar` yuvası için. */
    extensions: Record<string, unknown>;
};

export default function OrderShow({
    order,
    statuses,
    paymentStatuses,
    carriers,
    extensions,
}: Props) {
    const [statusOpen, setStatusOpen] = useState(false);
    const isSuperAdmin = useIsSuperAdmin();
    const canChangeLot = useCan('panel.shop.orders.change-lot') || isSuperAdmin;
    const canRefund = useCan('panel.shop.payments.refund') || isSuperAdmin;
    const [paymentOpen, setPaymentOpen] = useState(false);

    // Inertia v3'te post/put varsayılanı preserveState: true; ödeme "Ödendi"
    // yapılınca sipariş durumu da sunucuda değiştiği için formlar sunucudaki
    // son değerlerle yeniden kurulur (eski değerin geri gönderilmesini önler).
    const formKey = [
        order.status,
        order.payment_status,
        order.carrier_id,
        order.tracking_number,
        order.admin_note,
    ].join('|');

    const itemCount = order.items.reduce((sum, i) => sum + i.quantity, 0);

    return (
        <>
            <Head title={t('Sipariş :number', { number: order.number })} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Satış') },
                        { title: t('Siparişler'), href: index() },
                        { title: `#${order.number}` },
                    ]}
                    title={t('Sipariş #:number', { number: order.number })}
                    badges={
                        <>
                            <OrderStatusBadge
                                status={order.status}
                                label={order.status_label}
                            />
                            <PaymentStatusBadge
                                status={order.payment_status}
                                label={t('Ödeme: :payment_status_label', {
                                    payment_status_label:
                                        order.payment_status_label,
                                })}
                            />
                            {order.stock_deducted && (
                                <Badge variant="outline">
                                    {t('Stok düşüldü')}
                                </Badge>
                            )}
                        </>
                    }
                    meta={
                        <>
                            <span>{formatDateTime(order.created_at)}</span>
                            <MetaDot />
                            <span>
                                {t(':item_count ürün', {
                                    item_count: itemCount,
                                })}
                            </span>
                            <MetaDot />
                            <span>
                                {t('Toplam')}{' '}
                                <span className="font-medium text-foreground tabular-nums">
                                    {order.grand_total_formatted}
                                </span>
                            </span>
                            <MetaDot />
                            <span>{order.payment_method_label}</span>
                            {order.due_date && (
                                <>
                                    <MetaDot />
                                    <span>
                                        {t('Vade :due_date', {
                                            due_date: formatDate(
                                                order.due_date,
                                            ),
                                        })}
                                    </span>
                                </>
                            )}
                        </>
                    }
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setPaymentOpen(true)}
                            >
                                <CreditCard data-icon="inline-start" />
                                {t('Ödeme')}
                            </Button>
                            <Button onClick={() => setStatusOpen(true)}>
                                <RefreshCw data-icon="inline-start" />
                                {t('Durumu değiştir')}
                            </Button>
                        </>
                    }
                />

                {order.status === 'awaiting_approval' && (
                    <OrderApprovalAlert orderId={order.id} />
                )}

                <EntityLayout>
                    <EntityMain>
                        <OrderItemsCard
                            order={order}
                            canChangeLot={canChangeLot}
                        />
                        <OrderPaymentsCard
                            order={order}
                            canRefund={canRefund}
                        />
                        <OrderHistoryCard histories={order.histories} />
                    </EntityMain>

                    <EntitySidebar>
                        <OrderCustomerCard order={order} />
                        <OrderContactCard order={order} />

                        <AddressCard
                            title={t('Teslimat adresi')}
                            address={order.shipping_address}
                        />
                        <AddressCard
                            title={t('Fatura adresi')}
                            address={order.billing_address}
                            billing
                        />

                        <OrderShippingCard order={order} />

                        <InvoiceCard order={order} />

                        <Slot
                            name="orders.show.sidebar"
                            order={order}
                            extensions={extensions}
                        />

                        <AdminNoteCard order={order} formKey={formKey} />

                        {order.ip_address && (
                            <p className="px-1 text-xs text-muted-foreground">
                                {t('IP: :ip_address', {
                                    ip_address: order.ip_address,
                                })}
                            </p>
                        )}
                    </EntitySidebar>
                </EntityLayout>
            </Page>

            <OrderStatusDialog
                order={order}
                statuses={statuses}
                carriers={carriers}
                formKey={formKey}
                open={statusOpen}
                onOpenChange={setStatusOpen}
            />

            <OrderPaymentDialog
                order={order}
                paymentStatuses={paymentStatuses}
                formKey={formKey}
                open={paymentOpen}
                onOpenChange={setPaymentOpen}
            />
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Siparişler'), href: index() },
    ],
};
