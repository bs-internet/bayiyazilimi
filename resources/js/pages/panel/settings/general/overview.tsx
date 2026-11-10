import { Head } from '@inertiajs/react';

import { SectionOverview } from '@/components/section-overview';
import type { OverviewSummary } from '@/components/section-overview';
import { t } from '@/lib/i18n';
import { overview } from '@/routes/panel/settings/general';

type Props = {
    summaries: Record<string, OverviewSummary>;
};

export default function GeneralOverview({ summaries }: Props) {
    return (
        <>
            <Head title={t('Genel Ayarlar')} />

            <div className="flex max-w-5xl flex-col gap-6">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">
                        {t('Genel bakış')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Sistem, firma ve site ayarları. Düzenlemek istediğiniz bölümü seçin.',
                        )}
                    </p>
                </div>

                <SectionOverview
                    parentKey="panel.settings.general"
                    overviewKey="panel.settings.general.overview"
                    summaries={summaries}
                />
            </div>
        </>
    );
}

GeneralOverview.layout = {
    breadcrumbs: [{ title: t('Genel Ayarlar'), href: overview() }],
};
