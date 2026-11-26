import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type CardBrand = 'visa' | 'mastercard' | 'amex' | 'troy' | null;

/** Kart numarasının başından kart ağı (yalnız görünüm için). */
export function cardBrand(number: string): CardBrand {
    if (/^4/.test(number)) {
        return 'visa';
    }

    if (/^(5[1-5]|2[2-7])/.test(number)) {
        return 'mastercard';
    }

    if (/^3[47]/.test(number)) {
        return 'amex';
    }

    if (/^9792/.test(number)) {
        return 'troy';
    }

    return null;
}

/** 4'lü gruplar (Amex 4-6-5). */
export function formatCardNumber(digits: string): string {
    if (cardBrand(digits) === 'amex') {
        return [digits.slice(0, 4), digits.slice(4, 10), digits.slice(10, 15)]
            .filter(Boolean)
            .join(' ');
    }

    return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
}

function BrandMark({ brand }: { brand: CardBrand }) {
    switch (brand) {
        case 'visa':
            return (
                <span className="text-2xl font-black tracking-tight italic">
                    VISA
                </span>
            );
        case 'mastercard':
            return (
                <span className="flex items-center" aria-label="Mastercard">
                    <span className="size-8 rounded-full bg-red-500/90" />
                    <span className="-ml-3 size-8 rounded-full bg-amber-400/90" />
                </span>
            );
        case 'amex':
            return (
                <span className="rounded-sm border border-white/70 px-1.5 text-sm font-black tracking-widest">
                    AMEX
                </span>
            );
        case 'troy':
            return (
                <span className="text-xl font-black tracking-wide lowercase">
                    troy
                </span>
            );
        default:
            return null;
    }
}

/**
 * Kart ekranındaki kart resmi: girilen bilgiler canlı görünür, güvenlik kodu
 * yazılırken kart arkasına döner. Kart bilgisi yalnız ekranda; hiçbir yere yazılmaz.
 */
export function CardVisual({
    number,
    holder,
    expiry,
    cvv,
    flipped,
    className,
}: {
    number: string;
    holder: string;
    expiry: string;
    cvv: string;
    flipped: boolean;
    /** Kart zemini (renk sınıfları); tema kendi rengini verebilir. */
    className?: string;
}) {
    const brand = cardBrand(number);
    const shown = formatCardNumber(number.padEnd(16, '•').slice(0, 19));
    const face = cn(
        'absolute inset-0 flex flex-col overflow-hidden rounded-2xl p-5 text-white shadow-xl backface-hidden',
        className ??
            'bg-linear-to-br from-slate-900 via-slate-700 to-slate-900',
    );

    return (
        <div
            className="mx-auto aspect-[1.586] w-full max-w-sm select-none perspective-distant"
            aria-hidden="true"
        >
            <div
                className={cn(
                    'relative size-full transition-transform duration-500 transform-3d',
                    flipped && 'rotate-y-180',
                )}
            >
                {/* Ön yüz */}
                <div className={face}>
                    <div className="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/10" />
                    <div className="pointer-events-none absolute -bottom-20 -left-10 size-56 rounded-full bg-white/5" />
                    <div className="relative flex items-start justify-between">
                        <span className="mt-1 h-8 w-11 rounded-md bg-linear-to-br from-amber-200 via-amber-300 to-amber-500 shadow-inner" />
                        <BrandMark brand={brand} />
                    </div>
                    <div className="relative mt-auto font-mono text-lg tracking-[0.12em] tabular-nums sm:text-xl">
                        {shown}
                    </div>
                    <div className="relative mt-3 flex items-end justify-between gap-3 text-[11px] uppercase">
                        <div className="min-w-0">
                            <div className="text-[9px] opacity-60">
                                {t('Kart Sahibi')}
                            </div>
                            <div className="truncate text-sm font-semibold tracking-wide">
                                {holder || t('AD SOYAD')}
                            </div>
                        </div>
                        <div className="shrink-0 text-right">
                            <div className="text-[9px] opacity-60">
                                {t('Son Kullanma')}
                            </div>
                            <div className="font-mono text-sm font-semibold">
                                {expiry || t('AA/YY')}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Arka yüz */}
                <div className={cn(face, 'rotate-y-180 px-0')}>
                    <div className="mt-3 h-10 w-full bg-black/80" />
                    <div className="mt-5 flex items-center gap-3 px-5">
                        <div className="h-9 flex-1 rounded-sm bg-white/85" />
                        <div className="rounded-sm bg-white px-3 py-1.5 font-mono text-sm text-slate-900">
                            {cvv.padEnd(brand === 'amex' ? 4 : 3, '•')}
                        </div>
                    </div>
                    <div className="mt-auto flex justify-end px-5">
                        <BrandMark brand={brand} />
                    </div>
                </div>
            </div>
        </div>
    );
}
