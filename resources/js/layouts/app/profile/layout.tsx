import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { edit, notifications, security, sessions } from '@/routes/app/profile';
import { edit as editAppearance } from '@/routes/app/profile/appearance';

import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Bilgilerim'),
        href: edit(),
        icon: null,
    },
    {
        title: t('Bildirimler'),
        href: notifications(),
        icon: null,
    },
    {
        title: t('Oturum Kayıtları'),
        href: sessions(),
        icon: null,
    },
    {
        title: t('Güvenlik'),
        href: security(),
        icon: null,
    },
    {
        title: t('Tercihler'),
        href: editAppearance(),
        icon: null,
    },
];

export default function AppProfileLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Bilgilerim')}
            description={t(
                'Profil bilgilerinizi ve hesap ayarlarınızı yönetin',
            )}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
