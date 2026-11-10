import type { PropsWithChildren } from 'react';

/**
 * Mağaza ve içerik ekranları kendi sayfa kalıbını kurar
 * (`@/components/admin/page`: Page, PageTitle, EntityLayout, ListLayout …);
 * bu düzen yalnızca geçiş katmanıdır.
 */
export default function PanelShopLayout({ children }: PropsWithChildren) {
    return <>{children}</>;
}
