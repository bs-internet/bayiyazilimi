import { UserMenuContent } from '@/components/app/user-menu-content';
import { TopBar } from '@/components/top-bar';
import { useAppAuth } from '@/hooks/use-app-auth';
import { notifications } from '@/routes/app/profile';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { user } = useAppAuth();

    return (
        <TopBar
            breadcrumbs={breadcrumbs}
            user={user}
            notificationsHref={notifications()}
            menu={user ? <UserMenuContent user={user} /> : null}
        />
    );
}
