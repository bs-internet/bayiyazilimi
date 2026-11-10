import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/** Menü kaydında "Ödemeler" öğesinin anahtarı. */
const PAYMENTS_KEY = 'panel.shop.ledger';

/**
 * Mağaza › Ödemeler: tahsilatlar, havale bildirimleri, çek / senet (açıksa) ve
 * geciken ödemeler. İç menü sidebar'daki "Ödemeler" öğesinin alt öğelerinden
 * kurulur (MenuRegistry, yetkiye ve ayara göre süzülmüş).
 */
function usePaymentItems(): NavItem[] {
    const { navigation } = usePage().props;

    for (const group of navigation ?? []) {
        const parent = group.items.find((item) => item.key === PAYMENTS_KEY);

        if (parent?.items?.length) {
            return parent.items.map((item) => ({
                key: item.key,
                title: item.title,
                // Menü adresleri mutlak gelir; etkin öğe eşleşmesi yol üzerinden yapılır.
                href: toUrl(item.href).replace(/^https?:\/\/[^/]+/, ''),
                icon: null,
            }));
        }
    }

    return [];
}

export default function PanelPaymentsLayout({ children }: PropsWithChildren) {
    return (
        <SectionLayout
            title={t('Ödemeler')}
            description={t(
                'Tahsilatlar, havale bildirimleri ve vadesi geçen borçlar.',
            )}
            items={usePaymentItems()}
        >
            {children}
        </SectionLayout>
    );
}
