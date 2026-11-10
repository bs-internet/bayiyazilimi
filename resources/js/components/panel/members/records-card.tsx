import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { SectionCard } from '@/components/admin/page';
import { Empty, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { t } from '@/lib/i18n';
import type { index as ordersIndex } from '@/routes/panel/shop/orders';

/** Sekme içeriği: kenara dayalı tablo kartı + "Tümünü gör" alt bağlantısı. */
export function RecordsCard({
    title,
    description,
    empty,
    count,
    total,
    allHref,
    children,
}: {
    title: string;
    description?: string;
    empty: string;
    count: number;
    total: number;
    allHref?: ReturnType<typeof ordersIndex> | null;
    children: ReactNode;
}) {
    return (
        <SectionCard
            title={title}
            description={description}
            flush
            footer={
                allHref && total > 0 ? (
                    <div className="flex w-full items-center justify-between gap-4 text-sm">
                        <span className="text-muted-foreground">
                            {count < total
                                ? t(
                                      'Son :count kayıt gösteriliyor (toplam :total)',
                                      { count, total },
                                  )
                                : t(':total kayıt', { total })}
                        </span>
                        <Link
                            href={allHref}
                            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                        >
                            {t('Tümünü gör')}
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </div>
                ) : undefined
            }
        >
            {count === 0 ? (
                <Empty className="py-10">
                    <EmptyHeader>
                        <EmptyTitle className="text-sm">{empty}</EmptyTitle>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className="[&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5">
                    {children}
                </div>
            )}
        </SectionCard>
    );
}
