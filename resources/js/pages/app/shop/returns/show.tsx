import { Head } from '@inertiajs/react';

import type { ReturnShowProps } from '@/components/account/return-show';
import { ReturnShowContent } from '@/components/account/return-show';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/returns';

export default function Page(props: ReturnShowProps) {
    return (
        <>
            <Head
                title={t('İade :number', {
                    number: props.returnRequest.number,
                })}
            />

            <PageBody>
                <ReturnShowContent area="app" {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('İadelerim'), href: index() }],
};
