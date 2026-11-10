import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Page, PageTitle } from '@/components/admin/page';
import { appKit } from '@/components/checkout/app-kit';
import { CheckoutForm } from '@/components/checkout/checkout-form';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/app';
import { index as cartIndex } from '@/routes/app/shop/cart';
import { index as checkoutIndex } from '@/routes/app/shop/checkout';
import type { CheckoutPageProps } from '@/types/checkout';

/**
 * Bayi paneli ödeme sayfası. Form gövdesi ve iş kuralları vitrinle ortaktır.
 */
export default function AppCheckout({
    quote,
    context,
    ...props
}: CheckoutPageProps) {
    void quote;
    const back = context
        ? { href: context.back_url, label: context.back_label }
        : { href: cartIndex().url, label: t('Sipariş ver') };

    return (
        <>
            <Head title={t('Siparişi tamamla')} />
            <Page>
                <PageTitle
                    title={context ? context.title : t('Siparişi tamamla')}
                    trail={[
                        { title: t('Panel'), href: dashboard() },
                        { title: back.label, href: back.href },
                        { title: t('Ödeme') },
                    ]}
                    description={t(
                        'Teslimat ve fatura adresinizi seçin, kargo ve ödeme yöntemini belirleyip siparişinizi onaylayın.',
                    )}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={back.href}>
                                <ArrowLeft data-icon="inline-start" />
                                {context ? t('Geri dön') : t('Sepete dön')}
                            </Link>
                        </Button>
                    }
                />
                <CheckoutForm kit={appKit} {...props} />
            </Page>
        </>
    );
}

AppCheckout.layout = {
    breadcrumbs: [
        { title: t('Sipariş ver'), href: cartIndex() },
        { title: t('Ödeme'), href: checkoutIndex() },
    ],
};
