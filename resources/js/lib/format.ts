/**
 * Ortak tarih/para biçimleme yardımcıları (tr-TR).
 * Çekirdeğe ait genel yardımcılar; modüller `@/lib/format` ile kullanır.
 */

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('tr-TR');
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('tr-TR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

/**
 * Ana para birimi (Genel Ayarlar › varsayılan para birimi). Ana birimle tutar
 * gösteren sayfalar (başlangıç, raporlar, müşteriler) sunucudan gelen kodu
 * render sırasında bildirir; varsayılan TRY.
 */
let baseCurrency = 'TRY';

export function setBaseCurrency(code: string | null | undefined): void {
    if (code) {
        baseCurrency = code;
    }
}

export function formatMoney(
    value: string | number | bigint | null | undefined,
    currency?: string,
): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const amount = typeof value === 'number' ? value : Number(value);

    if (Number.isNaN(amount)) {
        return String(value);
    }

    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: currency ?? baseCurrency,
        minimumFractionDigits: 2,
    }).format(amount);
}

/**
 * Sayı (oran, desi vb.) tr-TR biçiminde, gereksiz sıfırsız: "22.50" → "22,5".
 * Sunucudan gelen kesin ondalık metni doğrudan biçimler.
 */
export function formatNumber(
    value: string | number | bigint | null | undefined,
    maximumFractionDigits = 2,
    minimumFractionDigits = 0,
): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const amount = typeof value === 'number' ? value : Number(value);

    if (Number.isNaN(amount)) {
        return String(value);
    }

    return new Intl.NumberFormat('tr-TR', {
        maximumFractionDigits,
        minimumFractionDigits,
    }).format(amount);
}
