import { Head } from '@inertiajs/react';

import { Page, PageTitle } from '@/components/admin/page';
import { SectionOverview } from '@/components/section-overview';
import type { OverviewSummary } from '@/components/section-overview';
import { t } from '@/lib/i18n';
import { index } from '@/routes/panel/tools/definitions';

type Props = {
    summaries: Record<string, OverviewSummary>;
};

export default function DefinitionsOverview({ summaries }: Props) {
    return (
        <>
            <Head title={t('Tanımlamalar')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Genel Bakış')}
                    description={t(
                        'Sistem genelinde kullanılan tanımlar. Düzenlemek istediğiniz bölümü seçin.',
                    )}
                />

                <SectionOverview
                    parentKey="panel.tools.definitions"
                    overviewKey="panel.tools.definitions.index"
                    summaries={summaries}
                    sort
                />
            </Page>
        </>
    );
}

DefinitionsOverview.layout = {
    breadcrumbs: [{ title: t('Tanımlamalar'), href: index() }],
};
