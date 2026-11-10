import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { index as countriesIndex } from '@/routes/panel/tools/definitions/countries';
import { index as currenciesIndex } from '@/routes/panel/tools/definitions/currencies';
import { index as languagesIndex } from '@/routes/panel/tools/definitions/languages';
import { index as taxesIndex } from '@/routes/panel/tools/definitions/taxes';
import type { NavItem } from '@/types';

/** Menü kaydında "Tanımlamalar" öğesinin anahtarı. */
const DEFINITIONS_KEY = 'panel.tools.definitions';

const fallbackItems: NavItem[] = [
    {
        title: t('Diller'),
        href: languagesIndex(),
        icon: null,
    },
    {
        title: t('Bölgeler'),
        href: countriesIndex(),
        icon: null,
    },
    {
        title: t('Para Birimleri'),
        href: currenciesIndex(),
        icon: null,
    },
    {
        title: t('Vergi Oranları'),
        href: taxesIndex(),
        icon: null,
    },
];

/** Bölümün giriş sayfası (Genel Bakış) menü anahtarı. */
const OVERVIEW_KEY = 'panel.tools.definitions.index';

/** İç menü: Genel Bakış en üstte, gerisi alfabetik. */
function byTitle(a: NavItem, b: NavItem): number {
    if (a.key === OVERVIEW_KEY || b.key === OVERVIEW_KEY) {
        return a.key === OVERVIEW_KEY ? -1 : 1;
    }

    return a.title.localeCompare(b.title, 'tr');
}

/**
 * Bölüm menüsü, sidebar'daki "Tanımlamalar" öğesinin alt öğelerinden kurulur (MenuRegistry,
 * yetkiye göre süzülmüş). Böylece modüllerin eklediği tanım ekranları (ör. kargo
 * firmaları, birimler) burada da görünür.
 */
function useDefinitionItems(): NavItem[] {
    const { navigation } = usePage().props;

    for (const group of navigation ?? []) {
        const parent = group.items.find((item) => item.key === DEFINITIONS_KEY);

        if (parent?.items?.length) {
            return parent.items
                .map((item) => ({
                    key: item.key,
                    title: item.title,
                    // Menü adresleri mutlak gelir; etkin öğe eşleşmesi yol üzerinden yapılır.
                    href: toUrl(item.href).replace(/^https?:\/\/[^/]+/, ''),
                    icon: null,
                }))
                .sort(byTitle);
        }
    }

    return [...fallbackItems].sort(byTitle);
}

export default function PanelDefinitionsLayout({
    children,
}: PropsWithChildren) {
    const items = useDefinitionItems();

    return (
        <SectionLayout
            title={t('Tanımlamalar')}
            description={t(
                'Sistem genelinde kullanılan tanımlama bilgilerini yönetin',
            )}
            items={items}
        >
            {children}
        </SectionLayout>
    );
}
