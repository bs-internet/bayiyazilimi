import { Head } from '@inertiajs/react';

import type { OrderShowProps } from '@/components/account/order-show';
import { OrderShowContent } from '@/components/account/order-show';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/orders';

export default function Page(props: OrderShowProps) {
    return (
        <>
            <Head
                title={t('Sipariş :number', { number: props.order.number })}
            />

            <PageBody>
                <OrderShowContent area="app" {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Siparişlerim'), href: index() }],
};
