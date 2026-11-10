import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { activity } from '@/routes/panel/tools';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Kullanıcı Etkinlikleri'),
        href: activity(),
        icon: null,
    },
];

export default function PanelActivityLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Kullanıcı Etkinlikleri')}
            description={t('Kullanıcı etkinliklerini takip edin')}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
