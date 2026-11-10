import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { index } from '@/routes/panel/settings/api-tokens';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    {
        title: t('API Anahtarları'),
        href: index(),
        icon: null,
    },
];

export default function PanelApiTokensLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('API Anahtarları')}
            description={t(
                'ERP, muhasebe ve özel entegrasyonların REST API erişimi',
            )}
            items={items}
        >
            {children}
        </SectionLayout>
    );
}
