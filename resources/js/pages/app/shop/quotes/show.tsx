import { Head } from '@inertiajs/react';

import type { QuoteShowProps } from '@/components/account/quote-show';
import { QuoteShowContent } from '@/components/account/quote-show';
import { Page as PageBody } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/quotes';

export default function Page(props: QuoteShowProps) {
    return (
        <>
            <Head title={t('Teklif :number', { number: props.quote.number })} />

            <PageBody embedded>
                <QuoteShowContent area="app" embedded {...props} />
            </PageBody>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: t('Tekliflerim'), href: index() }],
};
