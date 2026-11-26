import { Head } from '@inertiajs/react';
import { Page, PageTitle } from '@/components/admin/page';
import { appKit } from '@/components/checkout/app-kit';
import { CardPaymentForm } from '@/components/checkout/card-payment-form';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/app';
import type { CardPaymentPageProps } from '@/types/card-payment';

/**
 * Bayi paneli kart ekranı: sipariş ya da cari tahsilat ödemesi.
 */
export default function AppCardPayment(props: CardPaymentPageProps) {
    const title = props.order
        ? t('Sipariş :number — Kartla Ödeme', { number: props.order.number })
        : t('Kartla Ödeme');

    return (
        <>
            <Head title={t('Kartla Ödeme')} />
            <Page>
                <PageTitle
                    title={title}
                    trail={[
                        { title: t('Panel'), href: dashboard() },
                        { title: t('Kartla Ödeme') },
                    ]}
                    description={t(
                        'Kart bilgilerinizi girin; ödeme bankanızın 3D Secure doğrulamasıyla tamamlanır.',
                    )}
                />
                <CardPaymentForm kit={appKit} {...props} />
            </Page>
        </>
    );
}
