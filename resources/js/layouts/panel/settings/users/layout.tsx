import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { index as permissionsIndex } from '@/routes/panel/settings/permissions';
import { index as rolesIndex } from '@/routes/panel/settings/roles';
import {
    create as usersCreate,
    index as usersIndex,
} from '@/routes/panel/settings/users';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Kullanıcılar'),
        href: usersIndex(),
        icon: null,
    },
    {
        title: t('Yeni Kullanıcı'),
        href: usersCreate(),
        icon: null,
    },
    {
        title: t('Görevler'),
        href: rolesIndex(),
        icon: null,
    },
    {
        title: t('Yetkiler'),
        href: permissionsIndex(),
        icon: null,
    },
];

export default function PanelUsersLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Kullanıcı Yönetimi')}
            description={t('Kullanıcıları, görevleri ve yetkileri yönetin')}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
