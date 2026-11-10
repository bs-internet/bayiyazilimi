import { Head } from '@inertiajs/react';

import type { OrdersIndexProps } from '@/components/account/orders-index';
import { OrdersIndexContent } from '@/components/account/orders-index';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/orders';

export default function Page(props: OrdersIndexProps) {
    return (
        <>
            <Head title={t('Siparişlerim')} />

            <PageBody>
                <OrdersIndexContent area="app" {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Siparişlerim'), href: index() }],
};
