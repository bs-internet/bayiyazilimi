import { Head } from '@inertiajs/react';

import type { ReturnCreateProps } from '@/components/account/return-create';
import { ReturnCreateContent } from '@/components/account/return-create';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/returns';

export default function Page(props: ReturnCreateProps) {
    return (
        <>
            <Head
                title={t('İade talebi — :number', {
                    number: props.order.number,
                })}
            />

            <PageBody>
                <ReturnCreateContent area="app" {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('İadelerim'), href: index() }],
};
