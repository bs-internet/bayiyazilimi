import type { OrderLotOption, OrderPick } from '@/components/shop/order-picks';
import type { OrderListItem, PaymentStatusValue } from '@/types';

export type Address = Record<string, string | null | undefined>;

export type OrderDetail = OrderListItem & {
    billing_address: Address;
    shipping_address: Address;
    customer_note: string | null;
    admin_note: string | null;
    cargo_company: string | null;
    carrier_id: string | null;
    carrier: string | null;
    total_desi: string;
    tracking_number: string | null;
    tracking_link: string | null;
    exchange_rate: string;
    paid_at: string | null;
    shipped_at: string | null;
    /** Cari borcunun vade tarihi. */
    due_date: string | null;
    /** Ödeme yöntemi farkı (havale indirimi −). */
    payment_adjustment_formatted: string | null;
    payment_adjustment_label: string | null;
    cancelled_at: string | null;
    invoiced_at: string | null;
    stock_deducted: boolean;
    ip_address: string | null;
    subtotal_formatted: string;
    gross_subtotal_formatted: string | null;
    tax_total_formatted: string;
    shipping_total_formatted: string;
    discount_total_formatted: string | null;
    quote: { id: string; number: string } | null;
    user_id: string | null;
    user: { name: string; email: string } | null;
    account: {
        id: string;
        title: string | null;
        type: string | null;
        type_label: string | null;
        orders_count: number | null;
    } | null;
    items: {
        id: string;
        product_id: string | null;
        image: string | null;
        sku: string | null;
        name: string;
        quantity: number;
        tax_rate: string;
        campaign: string | null;
        discount_formatted: string | null;
        unit_price_formatted: string;
        tax_amount_formatted: string;
        line_total_formatted: string;
        /** Stoğun düştüğü depo / lokasyon / lotlar (toplama bilgisi). */
        tracking: 'none' | 'lot' | 'serial';
        picks: OrderPick[];
        /** Lot değiştirme için stokta olan lotlar. */
        lot_options: OrderLotOption[];
    }[];
    histories: {
        id: string;
        created_at: string;
        status_label: string | null;
        note: string | null;
        notify_customer: boolean;
        user: string | null;
    }[];
    payments: {
        id: string;
        created_at: string;
        provider: string;
        method_label: string;
        installment: number;
        fee_formatted: string | null;
        merchant_oid: string;
        amount: string;
        refundable: boolean;
        refunded_formatted: string | null;
        /** Sağlayıcı iadeleri (en yeni önce). */
        refunds: OrderPaymentRefund[];
        amount_formatted: string;
        status: PaymentStatusValue;
        status_label: string;
        failed_reason: string | null;
        paid_at: string | null;
    }[];
};

export type OrderPayment = OrderDetail['payments'][number];

export type OrderPaymentRefund = {
    id: string;
    created_at: string;
    amount_formatted: string;
    status: 'pending' | 'succeeded' | 'failed';
    status_label: string;
    failed_reason: string | null;
    user: string | null;
    /** Bir süredir "İşlemde": elle sonuçlandırılabilir. */
    resolvable: boolean;
};
