import { Head } from '@inertiajs/react';

import type { PaymentsIndexProps } from '@/components/account/payments-index';
import { PaymentsIndexContent } from '@/components/account/payments-index';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/payments';

export default function Page(props: PaymentsIndexProps) {
    return (
        <>
            <Head title={t('Cari Hesabım')} />

            <PageBody>
                <PaymentsIndexContent
                    area="app"
                    title={t('Cari Hesabım')}
                    description={t(
                        'Bakiyeniz, açık borçlarınız, ödemeleriniz ve hesap ekstreniz.',
                    )}
                    {...props}
                />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Cari Hesabım'), href: index() }],
};
