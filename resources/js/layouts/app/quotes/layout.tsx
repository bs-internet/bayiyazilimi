import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { index as quoteListIndex } from '@/routes/app/shop/quote-list';
import { index as quotesIndex } from '@/routes/app/shop/quotes';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: t('Teklif listem'),
        href: quoteListIndex(),
        icon: null,
    },
    {
        title: t('Tekliflerim'),
        href: quotesIndex(),
        icon: null,
    },
];

/**
 * Bayi paneli › Teklifler: teklif listesi (talep hazırlama) ve gelen /
 * gönderilen teklifler tek bölümde, iç menüyle.
 */
export default function AppQuotesLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            bare
            title={t('Teklifler')}
            description={t(
                'Teklif listenizden talep oluşturun; size gönderilen teklifleri onaylayın, revize isteyin ya da reddedin.',
            )}
            items={sidebarNavItems}
        >
            {children}
        </SectionLayout>
    );
}
