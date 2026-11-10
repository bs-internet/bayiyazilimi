import { Head, Link } from '@inertiajs/react';
import { LogIn, Wrench } from 'lucide-react';
import { useBranding } from '@/hooks/use-branding';
import { t } from '@/lib/i18n';
import { login } from '@/routes';

type Props = {
    message: string | null;
};

/**
 * Bakım modu sayfası (Genel Ayarlar › Site Erişimi). Yöneticiler giriş
 * yaparak siteyi kullanmaya devam edebilir.
 */
export default function Maintenance({ message }: Props) {
    const branding = useBranding();

    return (
        <>
            <Head title={t('Bakım çalışması')} />

            <div className="flex min-h-svh flex-col items-center justify-center bg-background px-6 py-12 text-center">
                <img
                    src={branding.logo}
                    alt={branding.name}
                    className="mb-10 h-12 w-auto object-contain dark:hidden"
                />
                <img
                    src={branding.logo_dark}
                    alt={branding.name}
                    className="mb-10 hidden h-12 w-auto object-contain dark:block"
                />

                <div className="flex w-full max-w-lg flex-col items-center gap-5 rounded-2xl border bg-card px-8 py-10 shadow-sm">
                    <span className="flex size-14 items-center justify-center rounded-full bg-brand-orange/15 text-brand-orange">
                        <Wrench className="size-7" />
                    </span>
                    <h1 className="text-2xl font-semibold">
                        {t('Bakım çalışması')}
                    </h1>
                    <p className="text-muted-foreground">
                        {message ||
                            t(
                                'Sitemizde bakım çalışması yapılmaktadır. Kısa süre içinde yeniden hizmetinizdeyiz.',
                            )}
                    </p>
                </div>

                <Link
                    href={login()}
                    className="mt-8 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <LogIn className="size-4" />
                    {t('Yönetici girişi')}
                </Link>
            </div>
        </>
    );
}
