import { Head } from '@inertiajs/react';
import { appKit } from '@/components/checkout/app-kit';
import { CardPaymentForm } from '@/components/checkout/card-payment-form';
import { PoweredBy } from '@/components/powered-by';
import { useBranding } from '@/hooks/use-branding';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { t } from '@/lib/i18n';
import type { CardPaymentPageProps } from '@/types/card-payment';

/**
 * Kart ekranı (mağaza; varsayılan görünüm). Etkin tema `themes/{tema}/shop/payment/card`
 * taşıyorsa onunki açılır. Bayi `app/shop/payment/card` sayfasını görür.
 */
export default function CardPayment(props: CardPaymentPageProps) {
    useFlashToast();
    const branding = useBranding();

    return (
        <div className="min-h-screen bg-muted/40">
            <Head title={t('Kartla Ödeme')} />
            <header className="border-b bg-background">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <img
                        src={branding.logo}
                        alt={branding.name}
                        className="h-9 w-auto object-contain"
                    />
                    <span className="text-sm text-muted-foreground">
                        {t('Güvenli ödeme')}
                    </span>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">
                    {props.order
                        ? t('Sipariş :number — Kartla Ödeme', {
                              number: props.order.number,
                          })
                        : t('Kartla Ödeme')}
                </h1>
                <CardPaymentForm kit={appKit} {...props} />
            </main>
            <footer className="pb-8 text-center">
                <PoweredBy />
            </footer>
        </div>
    );
}
