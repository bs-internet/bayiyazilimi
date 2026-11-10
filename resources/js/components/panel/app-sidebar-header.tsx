import { UserMenuContent } from '@/components/panel/user-menu-content';
import { TopBar } from '@/components/top-bar';
import { usePanelAuth } from '@/hooks/use-panel-auth';
import { notifications } from '@/routes/panel/profile';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { user } = usePanelAuth();

    return (
        <TopBar
            breadcrumbs={breadcrumbs}
            user={user}
            notificationsHref={notifications()}
            menu={user ? <UserMenuContent user={user} /> : null}
        />
    );
}
