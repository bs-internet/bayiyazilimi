import {
    add,
    div,
    isPositive,
    line as decimalLine,
    times,
    toFixed,
    toInput,
    withoutRate,
    withRate,
} from '@/lib/decimal';
import type { DecimalInput } from '@/lib/decimal';

export type PickerProduct = {
    id: string;
    sku: string | null;
    name: string;
    unit: string | null;
    image: string | null;
    tax_rate: string;
    price: string;
    stock: number;
};

export type Line = {
    product_id: string;
    sku: string | null;
    name: string;
    unit: string | null;
    image: string | null;
    tax_rate: string;
    quantity: number | string;
    /** KDV hariç teklif birim fiyatı (sunucuya giden; boşsa fiyatlanmamış). */
    unit_price: string;
    /** KDV dahil birim fiyat (yalnız ekranda). */
    gross_price: string;
    /** Satır toplamı (KDV dahil); girilirse miktara bölünür. */
    total_price: string;
    /** Talep anındaki (ya da eklendiğindeki) site fiyatı, KDV hariç. */
    list_price: string | null;
    stock: number | null;
};

export type LineField =
    'quantity' | 'unit_price' | 'tax_rate' | 'gross_price' | 'total_price';

export type QuoteTotals = {
    subtotal: string;
    tax: string;
    shipping: string;
    total: string;
    unpriced: number;
};

// Hesaplar kesin ondalıkla (@/lib/decimal), sunucudaki QuoteService / Money ile aynı kural.
export const blank = (value: DecimalInput): boolean =>
    value === null || value === undefined || String(value).trim() === '';

export const grossOf = (net: DecimalInput, rate: DecimalInput): string =>
    blank(net) ? '' : toInput(withRate(net, rate, 2), 2);

export const netOf = (gross: DecimalInput, rate: DecimalInput): string =>
    blank(gross) ? '' : toInput(withoutRate(gross, rate, 4), 4);

/**
 * Bir alan değişince diğerleri: KDV'siz ↔ KDV'li fiyat ↔ satır toplamı
 * birbirinden hesaplanır; KDV oranı değişince KDV'siz fiyat sabit kalır.
 */
export function recalc(line: Line, field: LineField, value: string): Line {
    const next = { ...line, [field]: value };
    const rate = next.tax_rate;
    const quantity = next.quantity;
    const withTotal = (l: Line): Line => ({
        ...l,
        total_price: blank(l.gross_price)
            ? ''
            : toInput(times(l.gross_price, quantity, 2), 2),
    });

    switch (field) {
        case 'unit_price':
        case 'tax_rate':
            return withTotal({
                ...next,
                gross_price: grossOf(next.unit_price, rate),
            });
        case 'gross_price':
            return withTotal({
                ...next,
                unit_price: netOf(next.gross_price, rate),
            });
        case 'total_price': {
            if (!isPositive(quantity) || blank(value)) {
                return next;
            }

            const gross = div(value, quantity, 8);

            return {
                ...next,
                gross_price: toInput(gross, 4),
                unit_price: netOf(gross, rate),
            };
        }
        default:
            return withTotal(next);
    }
}

/** Sunucudan gelen kalemi ekran satırına çevirir (KDV'li fiyat ve toplam hesaplanır). */
export const lineFromItem = (
    item: Omit<Line, 'gross_price' | 'total_price'>,
): Line =>
    recalc(
        { ...item, gross_price: '', total_price: '' },
        'unit_price',
        blank(item.unit_price) ? '' : toInput(item.unit_price, 4),
    );

/** Seçiciden eklenen ürün: miktar 1, teklif fiyatı liste fiyatıyla başlar. */
export const lineFromProduct = (product: PickerProduct): Line =>
    recalc(
        {
            product_id: product.id,
            sku: product.sku,
            name: product.name,
            unit: product.unit,
            image: product.image,
            tax_rate: product.tax_rate,
            quantity: 1,
            unit_price: '',
            gross_price: '',
            total_price: '',
            list_price: product.price,
            stock: product.stock,
        },
        'unit_price',
        toInput(product.price, 4),
    );

// Fiyatı girilmemiş kalemde liste fiyatı kullanılır (sunucudaki hesapla aynı).
export function computeTotals(
    items: Line[],
    shippingTotal: number | string,
): QuoteTotals {
    let subtotal = '0';
    let tax = '0';
    let unpriced = 0;

    for (const line of items) {
        if (blank(line.unit_price)) {
            unpriced++;
        }

        // Sunucu gibi: birim fiyat 4 haneye yuvarlanır, satır Money::line ile.
        const unit = blank(line.unit_price)
            ? (line.list_price ?? 0)
            : toFixed(line.unit_price, 4);
        const row = decimalLine(unit, line.quantity, line.tax_rate);
        subtotal = add(subtotal, row.net);
        tax = add(tax, row.tax);
    }

    const shipping = toFixed(shippingTotal);

    return {
        subtotal,
        tax,
        shipping,
        total: add(subtotal, tax, shipping),
        unpriced,
    };
}

// Liste fiyatını teklif fiyatı olarak kopyalar (yalnız boş olanlara).
export const fillListPrices = (items: Line[]): Line[] =>
    items.map((line) =>
        blank(line.unit_price) && line.list_price !== null
            ? recalc(line, 'unit_price', toInput(line.list_price, 4))
            : line,
    );
