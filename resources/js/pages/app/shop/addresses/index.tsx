import { Head } from '@inertiajs/react';

import type { AddressesIndexProps } from '@/components/account/addresses-index';
import { AddressesIndexContent } from '@/components/account/addresses-index';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/addresses';

export default function Page(props: AddressesIndexProps) {
    return (
        <>
            <Head title={t('Adreslerim')} />

            <AddressesIndexContent area="app" {...props} />
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Adreslerim'), href: index() }],
};
