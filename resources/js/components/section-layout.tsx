import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { useActiveNavHref } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * Bölüm düzeni (Müşteriler, Ayarlar, Araçlar, Bilgilerim …): gri zeminde
 * başlık, solda bölüm menüsü kartı (mobilde yatay kaydırılır), sağda içerik
 * kartı. Tek öğeli bölümlerde menü gösterilmez.
 */
export function SectionLayout({
    title,
    description,
    items,
    bare = false,
    nav = 'side',
    children,
}: {
    title: string;
    description?: string;
    items: NavItem[];
    /** true: içerik dış kart olmadan (sayfa kendi kartlarını dizer). */
    bare?: boolean;
    /** 'top': menü başlığın altında sekme şeridi; içerik tam genişlik (geniş tablolu bölümler). */
    nav?: 'side' | 'top';
    children: ReactNode;
}) {
    const activeHref = useActiveNavHref(items.map((item) => item.href));
    const showNav = items.length > 1;

    if (nav === 'top') {
        return (
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader title={title} description={description} />

                {showNav && (
                    <nav
                        aria-label={title}
                        className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-card"
                    >
                        {/* Sekmeler alttaki renkli çizgiye oturur; etkin sekme üst köşeleri yuvarlak, renkli. */}
                        <div className="flex [scrollbar-width:none] items-end gap-1 overflow-x-auto border-b-2 border-primary [&::-webkit-scrollbar]:hidden">
                            {items.map((item) => {
                                const active = toUrl(item.href) === activeHref;

                                return (
                                    <Link
                                        key={toUrl(item.href)}
                                        href={item.href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex shrink-0 items-center justify-center gap-2 rounded-t-lg px-4 py-2.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-muted hover:text-foreground lg:flex-1',
                                            active &&
                                                'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground',
                                        )}
                                    >
                                        {item.icon && (
                                            <item.icon className="size-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                );
                            })}
                        </div>
                    </nav>
                )}

                <section
                    className={cn(
                        'min-w-0',
                        !bare &&
                            'rounded-xl border bg-card p-4 shadow-sm md:p-6',
                    )}
                >
                    <div className="space-y-12">{children}</div>
                </section>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader title={title} description={description} />

            <div
                className={cn(
                    'grid items-start gap-6',
                    showNav && 'lg:grid-cols-[13rem_minmax(0,1fr)]',
                )}
            >
                {showNav && (
                    <nav
                        aria-label={title}
                        className="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:sticky lg:top-20 lg:mx-0 lg:flex-col lg:overflow-visible lg:rounded-xl lg:border lg:bg-card lg:p-2 lg:shadow-sm"
                    >
                        {items.map((item) => {
                            const active = toUrl(item.href) === activeHref;

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
                                        'max-lg:border max-lg:bg-card',
                                        active &&
                                            'bg-primary/10 text-primary hover:bg-primary/10 hover:text-primary max-lg:border-primary/30',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                )}

                <section
                    className={cn(
                        'min-w-0',
                        !bare &&
                            'rounded-xl border bg-card p-4 shadow-sm md:p-6',
                    )}
                >
                    <div className="space-y-12">{children}</div>
                </section>
            </div>
        </div>
    );
}
