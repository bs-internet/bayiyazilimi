import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, TriangleAlert } from 'lucide-react';
import { createElement } from 'react';

import { resolveNavIcon } from '@/lib/navigation-icons';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export type OverviewSummary = { text: string; warning: boolean };

/**
 * Bölüm giriş sayfası (Tanımlamalar, Genel Ayarlar): menüdeki bölümün alt
 * öğelerini (yetkiye göre süzülmüş `navigation`) kart olarak listeler. Kart
 * özeti sunucudan menü anahtarıyla gelir; sonradan eklenen bölüm kendiliğinden
 * kart olur.
 */
export function SectionOverview({
    parentKey,
    overviewKey,
    summaries,
    sort = false,
}: {
    /** Sidebar'daki bölüm öğesinin anahtarı. */
    parentKey: string;
    /** Giriş sayfasının kendi menü anahtarı (kart olarak gösterilmez). */
    overviewKey: string;
    summaries: Record<string, OverviewSummary>;
    /** true: kartlar alfabetik. */
    sort?: boolean;
}) {
    const { navigation } = usePage().props;
    const parent = (navigation ?? [])
        .flatMap((group) => group.items)
        .find((item) => item.key === parentKey);

    const items: NavItem[] = (parent?.items ?? []).filter(
        (item) => item.key !== overviewKey,
    );

    if (sort) {
        items.sort((a, b) => a.title.localeCompare(b.title, 'tr'));
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {items.map((item) => {
                const icon = resolveNavIcon(item.icon);
                const summary = item.key ? summaries[item.key] : undefined;

                return (
                    <Link
                        key={item.key ?? item.title}
                        href={item.href}
                        className="group flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/40"
                    >
                        <div className="flex items-center gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary [&_svg]:size-4">
                                {icon ? createElement(icon) : null}
                            </span>
                            <span className="min-w-0 flex-1 font-medium">
                                {item.title}
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                        </div>
                        {item.description ? (
                            <p className="text-sm text-muted-foreground">
                                {item.description}
                            </p>
                        ) : null}
                        {summary?.text ? (
                            <p
                                className={cn(
                                    'mt-auto flex items-center gap-1.5 border-t pt-3 text-xs',
                                    summary.warning
                                        ? 'font-medium text-amber-700 dark:text-amber-400'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {summary.warning ? (
                                    <TriangleAlert className="size-3.5 shrink-0" />
                                ) : null}
                                {summary.text}
                            </p>
                        ) : null}
                    </Link>
                );
            })}
        </div>
    );
}
