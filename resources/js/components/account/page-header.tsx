import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { AccountArea } from '@/components/account/routes';
import { PageTitle } from '@/components/admin/page';
import Heading from '@/components/heading';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/app';

/**
 * Üye alanı içerik başlığı: başlık + açıklama, sağda isteğe bağlı eylemler.
 */
export function AccountPageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    if (!actions) {
        return <Heading title={title} description={description} />;
    }

    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <Heading title={title} description={description} />
            <div className="flex flex-wrap items-center gap-2">{actions}</div>
        </div>
    );
}

type Href = NonNullable<InertiaLinkProps['href']>;

/**
 * Alana göre sayfa başlığı. `/app` (bayi) panel kalıbındaki PageTitle'ı
 * kullanır (konum izi + başlık + rozetler + meta + eylemler); vitrindeki
 * "Hesabım" çerçevesi kendi konum çubuğunu çizdiğinden orada iz gösterilmez.
 */
export function AccountTitle({
    area,
    title,
    parent,
    badges,
    meta,
    description,
    actions,
    compact = false,
}: {
    area: AccountArea;
    /** Bölüm düzeni içinde: küçük başlık, konum izi yok (bayi alanı). */
    compact?: boolean;
    title: string;
    /** Detay sayfalarında üst liste (örn. Siparişlerim). */
    parent?: { title: string; href: Href };
    badges?: ReactNode;
    meta?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    if (area === 'app') {
        return (
            <PageTitle
                compact={compact}
                trail={
                    compact
                        ? []
                        : parent
                          ? [
                                { title: t('Başlangıç'), href: dashboard() },
                                parent,
                                { title },
                            ]
                          : [
                                { title: t('Başlangıç'), href: dashboard() },
                                { title },
                            ]
                }
                title={title}
                badges={badges}
                meta={meta}
                description={description}
                actions={actions}
            />
        );
    }

    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h2 className="text-xl font-bold tracking-tight">
                        {title}
                    </h2>
                    {badges && (
                        <div className="flex flex-wrap items-center gap-2">
                            {badges}
                        </div>
                    )}
                </div>
                {meta && (
                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        {meta}
                    </div>
                )}
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
