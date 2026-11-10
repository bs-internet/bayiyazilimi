import { usePage } from '@inertiajs/react';
import {
    Banknote,
    FileText,
    Package,
    Tag,
    Undo2,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { SectionLayout } from '@/components/section-layout';
import { t } from '@/lib/i18n';
import type { NavItem } from '@/types';

type ReportLink = { key: string; title: string; href: string };

const icons: Record<string, LucideIcon> = {
    sales: Banknote,
    products: Package,
    customers: Users,
    stock: Warehouse,
    quotes: FileText,
    campaigns: Tag,
    returns: Undo2,
};

/** Sekmede "Rapor" sözcüğü tekrar etmesin: "Satış Raporu" → "Satış". */
const shortTitle = (title: string) => title.replace(/\s+Raporu$/u, '');

/**
 * Müşteriler › Raporlar: başlığın altında rapor sekmeleri (kullanıcının
 * görebildiği raporlar; sayfa props'undan gelir).
 */
export default function PanelReportsLayout({ children }: PropsWithChildren) {
    const reports = (usePage().props.reports ?? []) as ReportLink[];

    const items: NavItem[] = reports.map((report) => ({
        title: shortTitle(report.title),
        href: report.href,
        icon: icons[report.key] ?? null,
    }));

    return (
        <SectionLayout
            bare
            nav="top"
            title={t('Raporlar')}
            description={t(
                "Satış, ürün, müşteri, stok, teklif, kampanya ve iade raporları; Excel'e aktarılabilir.",
            )}
            items={items}
        >
            {children}
        </SectionLayout>
    );
}
