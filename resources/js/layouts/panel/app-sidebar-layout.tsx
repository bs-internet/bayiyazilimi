import { AppShell } from '@/components/app-shell';
import { AppContent } from '@/components/panel/app-content';
import { AppFooter } from '@/components/panel/app-footer';
import { AppSidebar } from '@/components/panel/app-sidebar';
import { AppSidebarHeader } from '@/components/panel/app-sidebar-header';
import { SystemUpdateBanner } from '@/components/panel/system-update-banner';
import type { PanelLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PanelLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SystemUpdateBanner />
                {children}
                <AppFooter />
            </AppContent>
        </AppShell>
    );
}
