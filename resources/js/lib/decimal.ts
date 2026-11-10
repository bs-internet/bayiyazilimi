/**
 * Kesin ondalık hesap (sunucudaki App\Support\Money ile aynı kurallar).
 *
 * Tutarlar sunucudan metin olarak gelir ("1234.50"); ön yüzde float ile
 * hesaplanmaz. Ara adımlar BigInt ile kesin tutulur, yalnız sonuç yuvarlanır:
 * HalfUp (yarım sıfırdan uzağa), tutarlar 2 hane. Böylece ekranda görünen
 * tutar sunucunun (ve muhasebeye aktarılan belgenin) hesapladığıyla aynıdır.
 */

export type DecimalInput = string | number | bigint | null | undefined;

/** n / 10^s */
type Dec = { n: bigint; s: number };

const TEN = 10n;

function pow10(s: number): bigint {
    return TEN ** BigInt(s);
}

/** "1234.5" / "1.234,50" / "1234,5" / 12.5 / null → kesin değer (geçersizse 0). */
function parse(value: DecimalInput): Dec {
    if (value === null || value === undefined || value === '') {
        return { n: 0n, s: 0 };
    }

    if (typeof value === 'bigint') {
        return { n: value, s: 0 };
    }

    let text =
        typeof value === 'number'
            ? Number.isFinite(value)
                ? value.toFixed(10)
                : '0'
            : value.trim().replace(/\s/g, '');

    // Türkçe yazım: "1.234,50" → "1234.50"; "12,5" → "12.5".
    if (text.includes(',')) {
        text = text.replace(/\./g, '').replace(',', '.');
    }

    const match = /^([+-]?)(\d*)(?:\.(\d*))?$/.exec(text);

    if (!match || (match[2] === '' && (match[3] ?? '') === '')) {
        return { n: 0n, s: 0 };
    }

    const fraction = match[3] ?? '';
    const n = BigInt((match[2] || '0') + fraction);

    return { n: match[1] === '-' ? -n : n, s: fraction.length };
}

function align(a: Dec, b: Dec): [bigint, bigint, number] {
    const s = Math.max(a.s, b.s);

    return [a.n * pow10(s - a.s), b.n * pow10(s - b.s), s];
}

/** n / d'yi `scale` haneye HalfUp yuvarlar (d > 0). */
function divRound(n: bigint, d: bigint, scale: number): Dec {
    const scaled = n * pow10(scale);
    const negative = scaled < 0n !== d < 0n;
    const absN = scaled < 0n ? -scaled : scaled;
    const absD = d < 0n ? -d : d;
    let q = absN / absD;

    if ((absN % absD) * 2n >= absD) {
        q += 1n;
    }

    return { n: negative ? -q : q, s: scale };
}

function round(d: Dec, scale: number): Dec {
    return d.s <= scale
        ? { n: d.n * pow10(scale - d.s), s: scale }
        : divRound(d.n, pow10(d.s), scale);
}

function str(d: Dec): string {
    const negative = d.n < 0n;
    const digits = (negative ? -d.n : d.n).toString().padStart(d.s + 1, '0');
    const body =
        d.s === 0 ? digits : `${digits.slice(0, -d.s)}.${digits.slice(-d.s)}`;

    return negative && d.n !== 0n ? `-${body}` : body;
}

/** Sabit haneli metin ("1234.50"); HalfUp. */
export function toFixed(value: DecimalInput, scale = 2): string {
    return str(round(parse(value), scale));
}

/** Giriş alanı için: en çok `scale` hane, gereksiz sıfırsız, virgüllü ("12,5"). */
export function toInput(value: DecimalInput, scale = 2): string {
    const fixed = toFixed(value, scale);
    const trimmed = fixed.includes('.')
        ? fixed.replace(/0+$/, '').replace(/\.$/, '')
        : fixed;

    return trimmed.replace('.', ',');
}

/** Kesin toplam (2 hane). */
export function add(...values: DecimalInput[]): string {
    const sum = values.reduce<Dec>(
        (acc, v) => {
            const [a, b, s] = align(acc, parse(v));

            return { n: a + b, s };
        },
        { n: 0n, s: 0 },
    );

    return str(round(sum, 2));
}

/** Fark: a − b (2 hane). */
export function sub(a: DecimalInput, b: DecimalInput): string {
    const [x, y, s] = align(parse(a), parse(b));

    return str(round({ n: x - y, s }, 2));
}

/** Çarpım: tutar × çarpan. */
export function times(
    amount: DecimalInput,
    factor: DecimalInput,
    scale = 2,
): string {
    const a = parse(amount);
    const f = parse(factor);

    return str(round({ n: a.n * f.n, s: a.s + f.s }, scale));
}

/** Yüzde: tutar × oran / 100 (ör. KDV, havale indirimi). */
export function percent(
    amount: DecimalInput,
    rate: DecimalInput,
    scale = 2,
): string {
    const a = parse(amount);
    const r = parse(rate);

    return str(divRound(a.n * r.n, pow10(a.s + r.s) * 100n, scale));
}

/** Oranla artırılmış tutar: tutar × (1 + oran / 100) (ör. KDV dahil fiyat). */
export function withRate(
    amount: DecimalInput,
    rate: DecimalInput,
    scale = 2,
): string {
    const a = parse(amount);
    const r = parse(rate);
    const base = pow10(r.s) * 100n;

    return str(divRound(a.n * (base + r.n), pow10(a.s) * base, scale));
}

/** Oransız tutar: tutar ÷ (1 + oran / 100) (ör. KDV dahilden KDV hariç fiyat). */
export function withoutRate(
    amount: DecimalInput,
    rate: DecimalInput,
    scale = 4,
): string {
    const a = parse(amount);
    const r = parse(rate);
    const base = pow10(r.s) * 100n;

    return str(divRound(a.n * base, pow10(a.s) * (base + r.n), scale));
}

/** Bölüm: tutar ÷ bölen; bölen sıfırsa sıfır. */
export function div(
    amount: DecimalInput,
    divisor: DecimalInput,
    scale = 2,
): string {
    const a = parse(amount);
    const d = parse(divisor);

    if (d.n === 0n) {
        return toFixed(0, scale);
    }

    // (a.n / 10^a.s) ÷ (d.n / 10^d.s) = a.n × 10^d.s ÷ (d.n × 10^a.s)
    return str(divRound(a.n * pow10(d.s), d.n * pow10(a.s), scale));
}

/** Belge satırı: net = birim × adet − indirim; KDV = net × oran; brüt = net + KDV. */
export function line(
    unitNet: DecimalInput,
    quantity: DecimalInput,
    taxRate: DecimalInput,
    discount: DecimalInput = 0,
): { net: string; tax: string; gross: string } {
    const gross = times(unitNet, quantity, 6);
    const net = sub(gross, discount);
    const tax = percent(net, taxRate);

    return { net, tax, gross: add(net, tax) };
}

/** a < b → -1, eşit → 0, a > b → 1. */
export function compare(a: DecimalInput, b: DecimalInput): -1 | 0 | 1 {
    const [x, y] = align(parse(a), parse(b));

    return x === y ? 0 : x < y ? -1 : 1;
}

export function isPositive(value: DecimalInput): boolean {
    return parse(value).n > 0n;
}

export function isNegative(value: DecimalInput): boolean {
    return parse(value).n < 0n;
}

export function isZero(value: DecimalInput): boolean {
    return parse(value).n === 0n;
}

/** Mutlak değer (2 hane). */
export function abs(value: DecimalInput): string {
    const d = parse(value);

    return str(round({ n: d.n < 0n ? -d.n : d.n, s: d.s }, 2));
}

/**
 * Yalnız sunum için sayı (grafik ekseni, kaydırıcı vb.). Hesapta kullanılmaz.
 */
export function toNumber(value: DecimalInput): number {
    return Number(str(parse(value)));
}
