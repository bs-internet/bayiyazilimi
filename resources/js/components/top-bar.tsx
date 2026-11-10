import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Bell, ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { Slot } from '@/lib/extensions';
import { t } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types';

/**
 * Panel ve üye alanının ortak üst çubuğu: menü düğmesi + breadcrumb solda;
 * modüllerin düğmeleri (`top-bar.actions` yuvası), bildirimler ve kullanıcı
 * menüsü sağda. Kaydırınca üstte kalır.
 */
export function TopBar({
    breadcrumbs = [],
    user,
    notificationsHref,
    menu,
}: {
    breadcrumbs?: BreadcrumbItem[];
    user: { name: string; email: string; avatar?: string | null } | null;
    notificationsHref: NonNullable<InertiaLinkProps['href']>;
    menu: ReactNode;
}) {
    const getInitials = useInitials();

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-card/80 md:px-6 print:hidden">
            <SidebarTrigger className="-ml-1" />
            <div className="hidden min-w-0 flex-1 sm:block">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-1">
                {/* Modüllerin düğmeleri (ör. vitrin: "Mağazayı aç") */}
                <Slot name="top-bar.actions" />

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={notificationsHref}>
                                <Bell />
                                <span className="sr-only">
                                    {t('Bildirimler')}
                                </span>
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{t('Bildirimler')}</TooltipContent>
                </Tooltip>

                {user && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="ml-1 h-10 gap-2 px-2"
                            >
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={user.avatar ?? undefined}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-40 truncate text-sm font-medium md:inline">
                                    {user.name}
                                </span>
                                <ChevronDown className="hidden size-4 text-muted-foreground md:inline" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-60 rounded-lg"
                        >
                            {menu}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </header>
    );
}
