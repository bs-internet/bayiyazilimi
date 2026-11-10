/**
 * Sepet / teklif listesi tipleri — bayi alanı (`/app`) ve vitrin ortak kullanır.
 * Backend: BasketPresenter (Basket::lines / totals).
 */

export type BasketLine = {
    id: string;
    /** Liste anahtarı (hediye satırı aynı ürünle çakışmasın). */
    key: string;
    /** Kampanya hediyesi: adet değiştirilemez, kaldırılamaz. */
    gift: boolean;
    slug: string;
    name: string;
    sku: string;
    brand: string | null;
    image: string;
    quantity: number;
    min_qty: number;
    max_qty: number | null;
    has_price: boolean;
    purchasable: boolean;
    in_stock: boolean;
    stock: number;
    unit_gross_formatted: string;
    line_gross: string;
    line_gross_formatted: string;
    /** Satıra uygulanan kampanyanın adı (yalnızca sepette). */
    campaign: string | null;
    /** Kampanya indirimi (KDV hariç). */
    discount: string;
    /** Kampanya indirimi, KDV dahil biçimlenmiş. */
    discount_formatted: string | null;
};

export type BasketTotals = {
    /** Kampanya indirimi (KDV hariç); ara toplamdan zaten düşülmüştür. */
    discount: string;
    discount_formatted: string | null;
    subtotal: string;
    subtotal_formatted: string;
    tax: string;
    tax_formatted: string;
    shipping: string;
    shipping_formatted: string;
    total: string;
    total_formatted: string;
    desi: string | null;
    carrier: string | null;
    currency: string;
    /** Uygulanan kampanya(lar) ve uyan ama uygulanmayanların sayısı. */
    campaign?: {
        name: string;
        summary: string;
        shipping: string | null;
        others: number;
    } | null;
    /** "Şu kadar daha ekleyin" teşviki: eksik tutar (KDV dahil) ve kampanya. */
    nudge?: {
        amount: string;
        amount_formatted: string;
        name: string;
        summary: string;
    } | null;
};
