import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { index } from '@/routes/panel/settings/extensions';
import type { NavItem } from '@/types';

/** Menü kaydında "Eklentiler" bölümünün anahtarı. */
const EXTENSIONS_KEY = 'panel.settings.extensions';

const fallbackItems: NavItem[] = [
    {
        title: t('Eklentiler'),
        href: index(),
        icon: null,
    },
];

/**
 * Bölüm menüsü, sidebar'daki "Eklentiler" öğesinin alt öğelerinden kurulur
 * (MenuRegistry, yetkiye göre süzülmüş): Eklentiler, Temalar.
 */
function useExtensionItems(): NavItem[] {
    const { navigation } = usePage().props;

    for (const group of navigation ?? []) {
        const parent = group.items.find((item) => item.key === EXTENSIONS_KEY);

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

export default function PanelExtensionsLayout({ children }: PropsWithChildren) {
    const items = useExtensionItems();

    return (
        <SectionLayout
            title={t('Eklentiler')}
            description={t('Sisteminize özellik katan eklentiler ve temalar')}
            items={items}
        >
            {children}
        </SectionLayout>
    );
}
