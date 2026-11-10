import type { ResolvedComponent } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppAccountLayout from '@/layouts/app/account/layout';
import AppContractsLayout from '@/layouts/app/contracts/layout';
import AppProfileLayout from '@/layouts/app/profile/layout';
import AppQuotesLayout from '@/layouts/app/quotes/layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PanelProfileLayout from '@/layouts/panel/profile/layout';
import PanelApiTokensLayout from '@/layouts/panel/settings/api-tokens/layout';
import PanelExtensionsLayout from '@/layouts/panel/settings/extensions/layout';
import PanelGeneralLayout from '@/layouts/panel/settings/general/layout';
import PanelUsersLayout from '@/layouts/panel/settings/users/layout';
import PanelCampaignsLayout from '@/layouts/panel/shop/campaigns/layout';
import PanelShopLayout from '@/layouts/panel/shop/layout';
import PanelPaymentsLayout from '@/layouts/panel/shop/payments/layout';
import PanelReportsLayout from '@/layouts/panel/shop/reports/layout';
import PanelActivityLayout from '@/layouts/panel/tools/activity/layout';
import PanelCacheLayout from '@/layouts/panel/tools/cache/layout';
import PanelDefinitionsLayout from '@/layouts/panel/tools/definitions/layout';
import PanelLayout from '@/layouts/panel-layout';

/**
 * Tarayıcı (app.tsx) ve sunucu (ssr.tsx) girişlerinin ortak Inertia ayarları:
 * başlık, sayfa çözücü, kalıcı düzenler ve uygulama sarmalayıcısı.
 */

const appName = import.meta.env.VITE_APP_NAME || 'Bayi Yazılımı';

// Vitrin sayfaları başlığa marka adını kendisi ekler; tekrar eklenmez.
export const title = (title: string): string => {
    if (!title) {
        return appName;
    }

    return title.includes(appName) ? title : `${title} - ${appName}`;
};

// Sayfalar iki kökten çözülür: çekirdeğin pages/'ı ve modüllerin kendi
// paketleri. Modül .tsx'leri host'a kopyalanmaz; buradan taranır. Globlar
// statik literaldir (import.meta.glob değişken kabul etmez); vendor listesi
// config/bayiyazilimi.php `module_vendors` ile aynı tutulur (bsinternet).
// Çekirdekte aynı yolda sayfa varsa modülünkünün yerine o kullanılır.
//
// Kural: modül sayfayı servis edileceği yola koyar —
// vendor/bsinternet/vitrin/resources/js/pages/shop/home.tsx  →  'shop/home'
export const resolve = async (name: string): Promise<ResolvedComponent> => {
    const pages = {
        ...import.meta.glob('./pages/**/*.tsx'),
        ...import.meta.glob([
            '../../vendor/bsinternet/*/resources/js/pages/**/*.tsx',
        ]),
    };

    const loader =
        pages[`./pages/${name}.tsx`] ??
        pages[
            Object.keys(pages).find((key) =>
                key.endsWith(`/resources/js/pages/${name}.tsx`),
            ) ?? ''
        ];

    if (!loader) {
        throw new Error(`Page not found: ${name}`);
    }

    return ((await loader()) as { default: ResolvedComponent }).default;
};

export const layout = (name: string) => {
    switch (true) {
        case name === 'welcome':
        case name === 'maintenance':
        case name.startsWith('install/'):
            return null;
        // Mağaza sayfaları (ön yüz eklentisi) ve tema sayfaları
        // (themes/{tema}/shop/...) düzenini kendisi tanımlar (`Sayfa.layout = …`).
        case name.startsWith('shop/'):
        case name.startsWith('themes/'):
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('app/profile/'):
            return [AppLayout, AppProfileLayout];
        case name.startsWith('app/account/'):
        case name.startsWith('app/shop/addresses/'):
            return [AppLayout, AppAccountLayout];
        case name.startsWith('app/shop/quotes/'):
        case name.startsWith('app/shop/quote-list/'):
            return [AppLayout, AppQuotesLayout];
        case name.startsWith('app/shop/contracts/'):
            return [AppLayout, AppContractsLayout];
        case name.startsWith('app/'):
            return [AppLayout];
        case name.startsWith('panel/profile/'):
            return [PanelLayout, PanelProfileLayout];
        case name.startsWith('panel/settings/general/'):
            return [PanelLayout, PanelGeneralLayout];
        case name.startsWith('panel/settings/api-tokens/'):
            return [PanelLayout, PanelApiTokensLayout];
        case name.startsWith('panel/settings/extensions/'):
            return [PanelLayout, PanelExtensionsLayout];
        case name.startsWith('panel/settings/users/'):
        case name.startsWith('panel/settings/roles/'):
        case name.startsWith('panel/settings/permissions/'):
            return [PanelLayout, PanelUsersLayout];
        case name.startsWith('panel/tools/activity/'):
            return [PanelLayout, PanelActivityLayout];
        case name.startsWith('panel/tools/cache/'):
            return [PanelLayout, PanelCacheLayout];
        case name.startsWith('panel/tools/definitions/'):
            return [PanelLayout, PanelDefinitionsLayout];
        case name.startsWith('panel/shop/campaigns/'):
            return [PanelLayout, PanelCampaignsLayout];
        case name.startsWith('panel/shop/reports/'):
            return [PanelLayout, PanelReportsLayout];
        // Cari Durum (ledger/index) Müşteriler altında; gerisi Ödemeler bölümü.
        case name.startsWith('panel/shop/ledger/') &&
            name !== 'panel/shop/ledger/index':
            return [PanelLayout, PanelPaymentsLayout];
        case name.startsWith('panel/shop/'):
            return [PanelLayout, PanelShopLayout];
        case name.startsWith('panel/'):
            return [PanelLayout];
        default:
            return AppLayout;
    }
};

export const withApp = (app: ReactElement): ReactElement => (
    <TooltipProvider delayDuration={0}>
        {app}
        <Toaster />
    </TooltipProvider>
);
