import type { BasketTotals } from './basket';
import type { ContractInfo } from './shop';

/**
 * Ödeme sayfası (vitrin `shop/checkout/index` ve bayi `app/shop/checkout/index`)
 * ortak prop'ları. Backend: Shop\CheckoutController::index / Shop\QuoteController::checkout.
 */
export type CheckoutCarrier = {
    id: string;
    name: string;
    logo: string | null;
    delivery_days: number | null;
    is_default: boolean;
    shipping: string;
    shipping_formatted: string;
    total: string;
    total_formatted: string;
};

/** Ödeme yöntemi (PaymentRegistry sürücüsü). */
export type CheckoutMethod = {
    value: string;
    label: string;
    description: string | null;
    /** İkon anahtarı: landmark, wallet, creditCard … */
    icon: string;
    /** Yönteme özel fark oranı (%): negatif indirim (havale), pozitif ek ücret. */
    adjustment_rate: string;
    adjustment_label: string | null;
    /** Kart sağlayıcısının taksit tablosu adresi (yoksa null). */
    installments_url?: string | null;
};

export type CheckoutLine = {
    name: string;
    quantity: number;
    total_formatted: string;
    campaign: string | null;
};

/** Kayıtlı adres (AddressController::present). Tür: teslimat ya da fatura. */
export type CheckoutSavedAddress = {
    id: string;
    title: string;
    type: 'shipping' | 'billing' | string;
    type_label: string;
    name: string;
    company: string | null;
    tax_office: string | null;
    tax_number: string | null;
    identity_number: string | null;
    phone: string | null;
    city_id: string | null;
    city: string | null;
    district_id: string | null;
    district: string | null;
    address: string;
    postal_code: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

export type CheckoutPrefill = {
    email: string;
    phone: string;
    billing: {
        type: string;
        name: string;
        company: string;
        address: string;
        city: string;
        district: string;
        postal_code: string;
    };
    shipping?: {
        name: string;
        phone: string;
        address: string;
        city: string;
        district: string;
        postal_code: string;
    };
};

export type CheckoutPageProps = {
    lines: CheckoutLine[];
    totals: BasketTotals;
    carriers: CheckoutCarrier[];
    /**
     * Ödeme yöntemine bağlı kampanya: yöntem seçilince gösterilecek sepet
     * (yalnız sonucu değiştiren yöntemler; yoksa `lines` / `totals` / `carriers`).
     */
    methodTotals?: Record<
        string,
        {
            lines: CheckoutLine[];
            totals: BasketTotals;
            carriers: CheckoutCarrier[];
        }
    >;
    methods: CheckoutMethod[];
    prefill: CheckoutPrefill;
    action: string;
    /** Tek kullanımlık ödeme anahtarı: aynı formun ikinci gönderimi sipariş açmaz. */
    checkoutToken?: string;
    quote: { number: string; valid_until: string | null; url: string } | null;
    /** Sepet dışı ödeme (ör. teklif): başlık ve geri bağlantısı. */
    context?: { title: string; back_label: string; back_url: string } | null;
    /** Ödeme formunda onaylatılan sözleşmeler; boşsa onay kutusu gösterilmez. */
    contracts: ContractInfo[];
    cities: { id: string; name: string }[];
    savedAddresses: CheckoutSavedAddress[];
    canSaveAddress: boolean;
};
