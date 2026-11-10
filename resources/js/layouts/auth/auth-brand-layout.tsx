import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    PackageCheck,
    Repeat2,
    ShieldCheck,
} from 'lucide-react';
import { PoweredBy } from '@/components/powered-by';
import { useBranding } from '@/hooks/use-branding';
import { t } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const HIGHLIGHTS = [
    {
        icon: PackageCheck,
        title: t('Sipariş takibi'),
        text: t(
            'Siparişlerinizin durumunu, kargo bilgisini ve faturalarını tek yerden izleyin.',
        ),
    },
    {
        icon: FileText,
        title: t('Teklif al'),
        text: t(
            'Projeleriniz için toplu teklif isteyin, onaylanan teklifi tek tıkla siparişe çevirin.',
        ),
    },
    {
        icon: Repeat2,
        title: t('Hızlı tekrar sipariş'),
        text: t(
            'Sık aldığınız ürünleri stok koduyla ya da önceki siparişlerinizden yeniden sipariş edin.',
        ),
    },
    {
        icon: ShieldCheck,
        title: t('Güvenli ödeme'),
        text: t(
            '256 bit SSL ile kredi kartı ya da havale/EFT ile güvenle ödeyin.',
        ),
    },
];

/**
 * Oturum ekranlarının düzeni: geniş ekranda solda marka paneli (Bayi
 * Yazılımı renkleri), sağda form; mobilde yalnız form. Açık/koyu temaya uyumludur.
 */
export default function AuthBrandLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const branding = useBranding();
    // Vitrin modülü kuruluysa vitrin adresi (Fortify oturum sayfaları verir); yoksa bağlantı gizli.
    const { storefrontUrl } = usePage().props as {
        storefrontUrl?: string | null;
    };

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="relative hidden overflow-hidden bg-brand-navy text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-brand-blue/35 blur-3xl"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -bottom-40 -left-24 size-96 rounded-full bg-brand-orange/20 blur-3xl"
                />

                <Link href={home()} className="relative w-fit">
                    <img
                        src={branding.logo_dark}
                        alt={branding.name}
                        className="h-12 w-auto object-contain"
                    />
                </Link>

                <div className="relative flex max-w-md flex-col gap-8">
                    <div className="flex flex-col gap-3">
                        <span className="h-1 w-12 rounded-full bg-brand-orange" />
                        <p className="text-3xl leading-tight font-semibold text-balance">
                            {branding.slogan ||
                                t(
                                    'Bayi ve e-ticaret işlemleriniz tek panelde.',
                                )}
                        </p>
                    </div>

                    <ul className="flex flex-col gap-5">
                        {HIGHLIGHTS.map(({ icon: Icon, title: t, text }) => (
                            <li key={t} className="flex gap-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-brand-orange ring-1 ring-white/15">
                                    <Icon className="size-5" />
                                </span>
                                <span className="flex flex-col gap-0.5">
                                    <span className="font-medium">{t}</span>
                                    <span className="text-sm text-white/70">
                                        {text}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-sm text-white/50">
                    © {new Date().getFullYear()} {branding.name}
                </p>
            </aside>

            <main className="flex flex-col px-6 py-8 sm:px-10">
                <div className="flex items-center justify-between">
                    <Link href={home()} className="lg:hidden">
                        <img
                            src={branding.logo}
                            alt={branding.name}
                            className="h-9 w-auto object-contain dark:hidden"
                        />
                        <img
                            src={branding.logo_dark}
                            alt={branding.name}
                            className="hidden h-9 w-auto object-contain dark:block"
                        />
                    </Link>
                    {storefrontUrl ? (
                        <Link
                            href={storefrontUrl}
                            className="ml-auto inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" />
                            {t('Mağazaya dön')}
                        </Link>
                    ) : null}
                </div>

                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="flex w-full max-w-sm flex-col gap-8">
                        <div className="flex flex-col gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        {children}
                    </div>
                </div>

                <PoweredBy className="text-center" />
            </main>
        </div>
    );
}
