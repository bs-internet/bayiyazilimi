import type { PageProps } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import type { NavItem } from '@/types';

type ContractPageProps = {
    items: { title: string; href: string }[];
};

/**
 * Bayi paneli › Sözleşmeler: aktif sözleşmeler iç menüde
 * (Tanımlamalar → Sözleşmeler'den yönetilir).
 */
export default function AppContractsLayout({ children }: PropsWithChildren) {
    const { items } = usePage<PageProps & ContractPageProps>().props;

    const nav: NavItem[] = items.map((item) => ({
        title: item.title,
        href: item.href,
        icon: null,
    }));

    return (
        <SectionLayout
            title={t('Sözleşmeler')}
            description={t('Satış, iade, üyelik ve KVKK metinleri.')}
            items={nav}
        >
            {children}
        </SectionLayout>
    );
}
