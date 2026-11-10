import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { cache } from '@/routes/panel/tools';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Önbellek Yönetimi'),
        href: cache(),
        icon: null,
    },
];

export default function PanelCacheLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Önbellek Yönetimi')}
            description={t('Önbellek ayarlarınızı ve performansınızı yönetin')}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
