import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { create, index } from '@/routes/panel/shop/campaigns';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Kampanyalar'),
        href: index(),
        icon: null,
    },
    {
        title: t('Yeni Kampanya'),
        href: create(),
        icon: null,
    },
];

/**
 * Müşteriler › Kampanyalar: koşullar sağlanınca sepete uygulanan indirimler.
 */
export default function PanelCampaignsLayout({ children }: PropsWithChildren) {
    // Oluşturma / düzenleme ekranı kendi kartlarını alt alta dizer; dış kart yok.
    const bare = usePage().component === 'panel/shop/campaigns/form';

    return (
        <SectionLayout
            bare={bare}
            title={t('Kampanyalar')}
            description={t(
                'Koşullar sağlanınca sepete uygulanan indirimler; sepette en avantajlı kampanya (ya da birlikte kullanılabilenler) uygulanır.',
            )}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
