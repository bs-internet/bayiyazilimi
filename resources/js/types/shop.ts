import type { CreditTermsValues } from './ledger';
import type { Option } from './option';
import type { Status } from './status';

/**
 * Mağaza (e-ticaret) panel tipleri — backend Resource'larını yansıtır.
 */

export type SaleMode = 'cart' | 'quote';

export type OrderStatusValue =
    | 'pending'
    | 'awaiting_approval'
    | 'processing'
    | 'shipped'
    | 'completed'
    | 'cancelled'
    | 'failed';

export type PaymentStatusValue =
    'pending' | 'partial' | 'paid' | 'failed' | 'refunded' | 'cancelled';

export type QuoteStatusValue =
    | 'draft'
    | 'pending'
    | 'revision'
    | 'sent'
    | 'approved'
    | 'ordered'
    | 'rejected'
    | 'cancelled';

export type ShopOption = Option<string>;

export type CategoryOption = ShopOption & { depth: number };

export type ProductListItem = {
    id: string;
    sku: string;
    barcode: string | null;
    model_number: string | null;
    name: string;
    slug: string;
    image: string | null;
    brand: string | null;
    category: string | null;
    price: string;
    price_formatted: string;
    currency_code: string | null;
    tax_rate: string;
    stock: number;
    track_stock: boolean;
    sale_mode: SaleMode;
    sale_mode_label: string;
    /** Ek rozetler (modüller; ör. vitrin "Bayi"). */
    badges: { label: string; variant?: string }[];
    /** Herkese açık ürün sayfası (vitrin modülü); yoksa null. */
    storefront_url: string | null;
    status: Status;
    updated_at: string;
};

export type ShopCategory = {
    id: string;
    parent_id: string | null;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    meta_title: string | null;
    meta_description: string | null;
    status: Status;
    sort_order: number;
    products_count: number | null;
    depth: number;
};

export type ShopBrand = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    status: Status;
    sort_order: number;
    products_count: number | null;
};

export type ShopWarehouse = {
    id: string;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    is_default: boolean;
    is_sellable: boolean;
    status: Status;
    sort_order: number;
    total_quantity: number | null;
};

export type StockMovementItem = {
    id: string;
    created_at: string;
    product_id: string;
    product_sku: string | null;
    product_name: string | null;
    warehouse: string | null;
    location: string | null;
    /** Lot / seri numarası (takipli üründe). */
    lot: string | null;
    lot_id: string | null;
    expires_at: string | null;
    type: string;
    type_label: string;
    quantity: number;
    balance_after: number;
    /** Referans türü: sipariş ya da mal kabul. */
    reference_kind: 'order' | 'receipt' | 'count' | null;
    reference_id: string | null;
    reference: string | null;
    note: string | null;
    user: string | null;
};

export type OrderListItem = {
    id: string;
    number: string;
    created_at: string;
    customer_name: string;
    email: string;
    phone: string;
    company: string | null;
    grand_total: string;
    grand_total_formatted: string;
    currency_code: string;
    status: OrderStatusValue;
    status_label: string;
    payment_status: PaymentStatusValue;
    payment_status_label: string;
    payment_method: string;
    payment_method_label: string;
    items_count: number | null;
    quote_id: string | null;
    account_id?: string | null;
    /** Siparişi veren hesabın türü (`dealer` / `customer`); yalnız listede. */
    account_type?: string | null;
    /** Fatura takibi: tamamlanan siparişin fatura no / tarihi. */
    invoice_number?: string | null;
    invoiced_at?: string | null;
};

export type QuoteListItem = {
    id: string;
    number: string;
    created_at: string;
    customer_name: string;
    company: string | null;
    email: string;
    phone: string | null;
    grand_total: string;
    grand_total_formatted: string;
    currency_code: string;
    status: QuoteStatusValue;
    status_label: string;
    valid_until: string | null;
    items_count: number | null;
    order_id: string | null;
    source: 'request' | 'admin';
};

export type CarrierRateRow = {
    min_desi: string | null;
    max_desi: string;
    price: string;
};

export type ShopCarrier = {
    id: string;
    code: string;
    name: string;
    logo_url: string | null;
    tracking_url: string | null;
    extra_desi_price: string;
    prices_include_tax: boolean;
    delivery_days: number | null;
    is_default: boolean;
    status: Status;
    sort_order: number;
    rates_count?: number;
    rates?: CarrierRateRow[];
};

export type ProductPick = { id: string; sku: string; name: string };

/** Tanımlamalar → Sözleşmeler kaydı (panel). */
export type Contract = {
    id: string;
    title: string;
    slug: string;
    content?: string | null;
    show_on_checkout: boolean;
    show_on_registration: boolean;
    status: Status;
    sort_order: number;
    updated_at: string | null;
};

/** Formda onaylatılan sözleşme (Contract::forForm; içerik temizlenmiş). */
export type ContractInfo = {
    title: string;
    slug: string;
    content: string;
};

/** Üye hesabının müşteri / bayi bilgisi (panel → Müşteriler). */
export type CustomerType = 'customer' | 'dealer';

export type MemberCustomerInfo = {
    /** Hesabı henüz açılmamış üyede null. */
    customer_type: CustomerType | null;
    company: string | null;
    dealer_group: { id: string; name: string; discount_rate: string } | null;
    /** Bayiye özel indirim (null → grubun oranı geçerli). */
    discount_rate: string | null;
    effective_discount_rate: string;
    /** Bayinin bölge deposu (yoksa tüm satılabilir depolar). */
    warehouse?: { id: string; name: string } | null;
    /** Bayiye özel cari çalışma şekli (boş alanlar gruptan). */
    credit_terms?: CreditTermsValues;
    /** Geçerli çalışma şekli özeti. */
    effective_terms?: {
        terms: string;
        label: string;
        limit: string | null;
        term_days: number;
    } | null;
    /** E-postasını doğrulamamış üyenin henüz hesabı yoktur. */
    account_id?: string | null;
    /** Yalnızca liste: ciro tanımına uyan sipariş sayısı ve TL toplamı. */
    orders_count?: number;
    orders_total?: string;
};

/** Panel → müşteri detayı: hesabın kayıtları (son N kayıt + toplam). */
export type MemberRecordList<T> = { data: T[]; total: number };

export type MemberOverview = {
    stats: {
        orders_count: number;
        total_spent: string;
        average_order: string | null;
        last_order_at: string | null;
    };
    orders: MemberRecordList<{
        id: string;
        number: string;
        status: string;
        status_label: string;
        payment_status: string;
        payment_status_label: string;
        items_count: number;
        grand_total: string;
        created_at: string | null;
    }>;

    quotes: MemberRecordList<{
        id: string;
        number: string;
        status: string;
        status_label: string;
        items_count: number;
        grand_total: string | null;
        created_at: string | null;
    }>;
    returns: MemberRecordList<{
        id: string;
        number: string;
        order_id: string;
        order_number: string | null;
        status: string;
        status_label: string;
        reason_label: string;
        items_count: number;
        refund_amount: string | null;
        created_at: string | null;
    }>;
    addresses: {
        id: string;
        title: string;
        type: 'shipping' | 'billing' | 'both';
        name: string;
        company: string | null;
        tax_office: string | null;
        tax_number: string | null;
        identity_number: string | null;
        phone: string | null;
        address: string;
        postal_code: string | null;
        district: string | null;
        city: string | null;
        country: string | null;
        is_default_shipping: boolean;
        is_default_billing: boolean;
    }[];
};

/** Hesabın fatura bilgileri (üyenin Hesabım ekranı). */
export type MemberBilling = {
    type: 'individual' | 'corporate';
    type_label: string;
    title: string | null;
    tax_office: string | null;
    tax_number: string | null;
    identity_number: string | null;
    invoice_email: string | null;
    address: string | null;
    postal_code: string | null;
    district: string | null;
    city: string | null;
    country: string | null;
};
