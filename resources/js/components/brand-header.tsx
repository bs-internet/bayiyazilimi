import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useBranding } from '@/hooks/use-branding';

/**
 * Sidebar başlığındaki marka alanı (beyaz şerit): açıkken logo, ikon
 * moduna daraltılınca favicon. Tek kaynak [[Branding]] helper'ı.
 */
export function BrandHeader({
    href,
}: {
    href: NonNullable<InertiaLinkProps['href']>;
}) {
    const branding = useBranding();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    size="lg"
                    asChild
                    className="hover:bg-transparent active:bg-transparent"
                >
                    <Link href={href} prefetch aria-label={branding.name}>
                        <img
                            src={branding.logo}
                            alt={branding.name}
                            className="h-9 w-auto max-w-full object-contain object-left group-data-[collapsible=icon]:hidden"
                        />
                        <img
                            src={branding.favicon}
                            alt=""
                            className="hidden size-8 object-cover object-left group-data-[collapsible=icon]:block"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
