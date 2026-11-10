import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { edit } from '@/routes/panel/settings/general';
import type { NavItem } from '@/types';

/** Menü kaydında "Genel Ayarlar" öğesinin anahtarı. */
const GENERAL_KEY = 'panel.settings.general';

const fallbackItems: NavItem[] = [
    {
        title: t('Genel Ayarlar'),
        href: edit(),
        icon: null,
    },
];

/**
 * Bölüm menüsü, sidebar'daki "Genel Ayarlar" öğesinin alt öğelerinden kurulur
 * (MenuRegistry, yetkiye göre süzülmüş). Modüllerin eklediği ayar sayfaları
 * (ör. Kurumsal Bilgiler) burada görünür.
 */
function useGeneralItems(): NavItem[] {
    const { navigation } = usePage().props;

    for (const group of navigation ?? []) {
        const parent = group.items.find((item) => item.key === GENERAL_KEY);

        if (parent?.items?.length) {
            return parent.items.map((item) => ({
                title: item.title,
                // Menü adresleri mutlak gelir; etkin öğe eşleşmesi yol üzerinden yapılır.
                href: toUrl(item.href).replace(/^https?:\/\/[^/]+/, ''),
                icon: null,
            }));
        }
    }

    return fallbackItems;
}

export default function PanelGeneralLayout({ children }: PropsWithChildren) {
    const items = useGeneralItems();

    return (
        <SectionLayout
            title={t('Genel Ayarlar')}
            description={t('Sistem genelindeki ayarları yönetin')}
            items={items}
        >
            {children}
        </SectionLayout>
    );
}
