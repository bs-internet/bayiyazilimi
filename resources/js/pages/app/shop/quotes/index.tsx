import { Head } from '@inertiajs/react';

import type { QuotesIndexProps } from '@/components/account/quotes-index';
import { QuotesIndexContent } from '@/components/account/quotes-index';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/quotes';

export default function Page(props: QuotesIndexProps) {
    return (
        <>
            <Head title={t('Tekliflerim')} />

            <PageBody embedded>
                <QuotesIndexContent area="app" embedded {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Tekliflerim'), href: index() }],
};
