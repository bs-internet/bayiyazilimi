import { usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { NavMain } from '@/components/app/nav-main';
import { BrandHeader } from '@/components/brand-header';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/app';

export function AppSidebar({ ...props }: ComponentProps<typeof Sidebar>) {
    const { navigation = [] } = usePage().props;

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border bg-white">
                <BrandHeader href={dashboard()} />
            </SidebarHeader>

            <SidebarContent>
                {navigation.map((group) => (
                    <NavMain
                        key={group.key}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>
        </Sidebar>
    );
}
