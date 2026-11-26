import type { BasketTotals } from './basket';
import type { CheckoutLine } from './checkout';

/** Siparişteki adres bloğu (ödeme formunda girilen). Boş alanlar null. */
export type CardPaymentAddress = {
    name: string | null;
    company: string | null;
    tax_office: string | null;
    tax_number: string | null;
    identity_number: string | null;
    phone: string | null;
    address: string | null;
    district: string | null;
    city: string | null;
    postal_code: string | null;
};

/**
 * Kart ekranı (`shop/payment/card`, `app/shop/payment/card`) prop'ları.
 * Backend: Support\Payments\CardFormGateway::renderPayment.
 */
export type CardPaymentPageProps = {
    payment: {
        reference: string;
        method: string;
        amount: string;
        amount_formatted: string;
        description: string;
    };
    /** Sipariş ödemesi değilse (cari tahsilat) null. */
    order: {
        /** Sipariş ödeme alınınca açılır; o zamana kadar null. */
        number: string | null;
        email: string;
        phone: string | null;
        billing: CardPaymentAddress;
        shipping: CardPaymentAddress;
        customer_note: string | null;
        carrier: string | null;
        lines: CheckoutLine[];
        totals: BasketTotals;
        adjustment: { label: string; amount_formatted: string } | null;
        grand_total_formatted: string;
    } | null;
    /** İmzalı "Ödeme Yap" adresi (düz form gönderimi: yanıt 3D sayfası olabilir). */
    action: string;
    csrfToken: string;
    backUrl: string;
    /** Sürücünün uyarısı (ör. önizleme). */
    notice: string | null;
    /** 1 = tek çekim (taksit seçimi gösterilmez). */
    maxInstallment: number;
};
