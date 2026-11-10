import { Head } from '@inertiajs/react';

import type { ReturnsIndexProps } from '@/components/account/returns-index';
import { ReturnsIndexContent } from '@/components/account/returns-index';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/returns';

export default function Page(props: ReturnsIndexProps) {
    return (
        <>
            <Head title={t('İadelerim')} />

            <PageBody>
                <ReturnsIndexContent area="app" {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('İadelerim'), href: index() }],
};
