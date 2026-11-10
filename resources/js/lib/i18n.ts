/**
 * Arayüz metinlerinin çevirisi (Laravel'in JSON çevirileriyle aynı düzen).
 *
 * Kaynak dil Türkçedir: anahtar, Türkçe metnin kendisidir —
 * PHP'de `__('Kaydet')`, ön yüzde `t('Kaydet')`. Tüm anahtarların kataloğu
 * `lang/tr.json` dosyasıdır (`php artisan lang:sync` günceller). Başka bir dil
 * için `lang/{locale}.json` eklenir ({"Kaydet": "Save"}); karşılığı olmayan
 * metin olduğu gibi (Türkçe) gösterilir.
 *
 * Yer tutucular Laravel ile aynıdır: `t(':count ürün', { count: 3 })`;
 * `:Name` ilk harfi, `:NAME` tümünü büyük yazar.
 */

type Catalog = Record<string, string>;

// Kataloglar: çekirdek (lang/) + modüller (paketlerin kendi lang/ klasörü).
// Globlar statik literaldir; vendor listesi config/bayiyazilimi.php `module_vendors`
// ile aynı tutulur. Kaynak dil (tr) çeviri gerektirmez; paketlenmez.
const catalogs = import.meta.glob<Catalog>(
    [
        '../../../lang/*.json',
        '../../../vendor/bsinternet/*/lang/*.json',
        '!**/tr.json',
    ],
    { eager: true, import: 'default' },
);

const SOURCE_LOCALE = 'tr';

/** Etkin dil: sunucunun <html lang> özniteliği (SSR'de kaynak dil). */
export function locale(): string {
    if (typeof document === 'undefined') {
        return SOURCE_LOCALE;
    }

    return (
        document.documentElement.lang.split('-')[0].toLowerCase() ||
        SOURCE_LOCALE
    );
}

const merged = new Map<string, Catalog | null>();

/** Bir dilin tüm katalogları tek sözlükte (modüller çekirdeğin üstüne). */
function catalog(code: string): Catalog | null {
    if (code === SOURCE_LOCALE) {
        return null;
    }

    if (!merged.has(code)) {
        const entries = Object.entries(catalogs)
            .filter(([path]) => path.endsWith(`/${code}.json`))
            .sort(
                ([a], [b]) =>
                    Number(a.includes('/vendor/')) -
                    Number(b.includes('/vendor/')),
            );

        merged.set(
            code,
            entries.length > 0
                ? Object.assign({}, ...entries.map(([, c]) => c))
                : null,
        );
    }

    return merged.get(code) ?? null;
}

export type Replacements = Record<string, string | number | null | undefined>;

function replace(text: string, replacements?: Replacements): string {
    if (!replacements) {
        return text;
    }

    // Uzun anahtarlar önce (":counter" ":count"tan önce değişsin).
    const keys = Object.keys(replacements).sort((a, b) => b.length - a.length);

    return keys.reduce((result, key) => {
        const value = String(replacements[key] ?? '');
        const upperFirst = key.charAt(0).toUpperCase() + key.slice(1);

        return result
            .replaceAll(`:${key.toUpperCase()}`, value.toLocaleUpperCase())
            .replaceAll(
                `:${upperFirst}`,
                value.charAt(0).toLocaleUpperCase() + value.slice(1),
            )
            .replaceAll(`:${key}`, value);
    }, text);
}

/** Çeviri: anahtar Türkçe metindir; karşılığı yoksa metin aynen döner. */
export function t(key: string, replacements?: Replacements): string {
    const translated = catalog(locale())?.[key];

    return replace(
        translated !== undefined && translated !== '' ? translated : key,
        replacements,
    );
}
