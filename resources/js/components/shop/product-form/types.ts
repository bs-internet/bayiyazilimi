import type { CategoryOption, SaleMode, ShopOption, Status } from '@/types';

/** Ürün adının yanındaki ek rozet (modüller ekler; ör. vitrin "Bayi"). */
export type ProductBadge = { label: string; variant?: string };

export type ProductFormData = {
    id: string;
    sku: string;
    barcode: string | null;
    model_number: string | null;
    name: string;
    slug: string;
    brand_id: string | null;
    category_id: string | null;
    short_description: string | null;
    description: string | null;
    notice: string | null;
    highlights: string;
    price: string;
    compare_price: string | null;
    currency_id: string | null;
    tax_rate: string;
    sale_mode: SaleMode;
    badges: ProductBadge[];
    /** Herkese açık ürün sayfası (vitrin modülü); yoksa null. */
    storefront_url: string | null;
    track_stock: boolean;
    allow_backorder: boolean;
    stock: number;
    /** Ürüne özel kritik stok eşiği; null → genel eşik. */
    min_stock: number | null;
    /** İzlenebilirlik: lot / seri numarası. */
    tracking: 'none' | 'lot' | 'serial';
    expiry_tracking: boolean;
    /** Ürünün kayıtlı lotu / seri numarası var mı (takip değişikliği uyarısı için). */
    has_lots: boolean;
    min_qty: number;
    max_qty: number | null;
    delivery_days: number;
    desi: string;
    unit: string;
    free_shipping: boolean;
    status: Status;
    sort_order: number;
    meta_title: string | null;
    meta_description: string | null;
    search_keywords: string | null;
    images: { id: string; url: string; name: string | null }[];
    stocks: {
        warehouse_id: string;
        warehouse: string;
        is_sellable: boolean;
        quantity: number;
    }[];
};

export type ProductFormProps = {
    product: ProductFormData | null;
    defaults: {
        currency_id: string | null;
        tax_rate: string;
        unit: string;
        low_stock_threshold: number;
        /** Lot / seri no / SKT takibi açık mı (Stok Ayarları). */
        traceability: boolean;
    };
    categories: CategoryOption[];
    brands: ShopOption[];
    saleModes: ShopOption[];
    /** Ürün adresi öneki ("/urun/"); vitrin modülü yoksa null. */
    productUrlPrefix: string | null;
    /** Modüllerin form verisi, modül anahtarıyla (`shop.products.form_props`). */
    extensions: Record<string, unknown>;
    currencies: ShopOption[];
    taxRates: string[];
    units: ShopOption[];
};
