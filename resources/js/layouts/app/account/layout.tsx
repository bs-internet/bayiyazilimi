import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { account } from '@/routes/app';
import { index as addressesIndex } from '@/routes/app/shop/addresses';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Hesabım'),
        href: account(),
        icon: null,
    },
    {
        title: t('Adreslerim'),
        href: addressesIndex(),
        icon: null,
    },
];

export default function AppAccountLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Hesabım')}
            description={t(
                'Fatura bilgilerinizi ve kayıtlı adreslerinizi yönetin',
            )}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
