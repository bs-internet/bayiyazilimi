import type { Auth } from '@/types/auth';
import type { NavigationGroup } from '@/types/navigation';

export type Branding = {
    name: string;
    slogan: string | null;
    logo: string;
    logo_dark: string;
    favicon: string;
    /** Bayi Yazılımı sürümü (müşteri markasından bağımsız). */
    version: string;
};

declare module '@inertiajs/core' {
    export interface PageProps {
        name: string;
        branding: Branding;
        auth: Auth;
        navigation: NavigationGroup[];
        /** Kurulu ve açık modül paketleri (ör. `bsinternet/vitrin`). */
        modules: string[];
        /** Super Admin: kod yeni sürümde, güncelleme adımları bekliyor (UpdateService). */
        systemUpdate: boolean;
        sidebarOpen: boolean;
        [key: string]: unknown;
    }
}
