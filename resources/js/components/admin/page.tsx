import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/*
 * Panel / üye alanı sayfa kalıpları (Stroyka benzeri):
 *
 *   <Page>
 *     <PageTitle trail={[...]} title="Sipariş #S123" meta={...} actions={...} />
 *     <EntityLayout>                      ← detay / düzenleme sayfaları
 *       <EntityMain> <SectionCard …/> … </EntityMain>
 *       <EntitySidebar> <SectionCard …/> … </EntitySidebar>
 *     </EntityLayout>
 *     — ya da —
 *     <ListLayout filters={<FilterPanel>…</FilterPanel>}>   ← liste sayfaları
 *       <TableCard toolbar={…} footer={<DataPagination …/>}> <Table …/> </TableCard>
 *     </ListLayout>
 *   </Page>
 */

type Href = NonNullable<InertiaLinkProps['href']>;

/**
 * Sayfa gövdesi: gri zeminde, geniş ekranda ortalanmış içerik.
 * `embedded`: bir bölüm düzeninin (ör. Tanımlamalar) içerik kartında çizilen
 * sayfalar için — dış boşluk ve genişlik sınırı olmadan.
 */
export function Page({
    children,
    className,
    size = 'xl',
    embedded = false,
}: {
    children: ReactNode;
    className?: string;
    size?: 'md' | 'lg' | 'xl' | 'full';
    embedded?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex w-full flex-col gap-6',
                !embedded && 'mx-auto p-4 md:p-6',
                !embedded && {
                    'max-w-4xl': size === 'md',
                    'max-w-6xl': size === 'lg',
                    'max-w-7xl': size === 'xl',
                },
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * Sayfa başlığı: küçük konum izi (trail), büyük başlık, başlığın yanında
 * rozetler, altında meta satırı (tarih · adet · toplam), sağda eylemler.
 */
export function PageTitle({
    title,
    trail = [],
    badges,
    meta,
    description,
    actions,
    compact = false,
}: {
    title: ReactNode;
    trail?: { title: string; href?: Href }[];
    badges?: ReactNode;
    meta?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    /** Bölüm düzeni içindeki sayfalar için küçük başlık (Tanımlamalar vb.). */
    compact?: boolean;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {trail.length > 0 && (
                    <nav
                        aria-label={t('Konum')}
                        className="mb-1.5 flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                    >
                        {trail.map((item, i) => (
                            <span
                                key={`${item.title}-${i}`}
                                className="flex items-center gap-1"
                            >
                                {i > 0 && <ChevronRight className="size-3" />}
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        className="hover:text-foreground"
                                    >
                                        {item.title}
                                    </Link>
                                ) : (
                                    <span>{item.title}</span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h1
                        className={
                            compact
                                ? 'text-base font-medium'
                                : 'text-2xl font-semibold tracking-tight'
                        }
                    >
                        {title}
                    </h1>
                    {badges && (
                        <div className="flex flex-wrap items-center gap-2">
                            {badges}
                        </div>
                    )}
                </div>
                {meta && (
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        {meta}
                    </div>
                )}
                {description && (
                    <p className="mt-1.5 max-w-3xl text-sm text-muted-foreground">
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

/** Meta satırında öğeler arası nokta ayırıcı. */
export function MetaDot() {
    return <span aria-hidden>·</span>;
}

/**
 * Detay / düzenleme sayfası: geniş ana sütun + dar yan sütun.
 * `stackUntil="2xl"`: dar içerik alanlarında (bölüm düzeni) yan sütun daha geç açılır.
 */
export function EntityLayout({
    children,
    stackUntil = 'lg',
}: {
    children: ReactNode;
    stackUntil?: 'lg' | '2xl';
}) {
    return (
        <div
            className={cn(
                'grid items-start gap-6',
                stackUntil === 'lg'
                    ? 'lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]'
                    : '2xl:grid-cols-[minmax(0,1fr)_20rem]',
            )}
        >
            {children}
        </div>
    );
}

export function EntityMain({ children }: { children: ReactNode }) {
    return <div className="flex min-w-0 flex-col gap-6">{children}</div>;
}

export function EntitySidebar({ children }: { children: ReactNode }) {
    return <aside className="flex min-w-0 flex-col gap-6">{children}</aside>;
}

/**
 * Başlıklı kart. `flush` tablo / liste gibi kenara dayanan içerik içindir
 * (iç boşluk yok, başlık altında çizgi).
 */
export function SectionCard({
    title,
    description,
    action,
    footer,
    flush = false,
    children,
    className,
}: {
    title?: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    footer?: ReactNode;
    flush?: boolean;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
            {(title || action) && (
                <CardHeader
                    className={cn('gap-1 px-5 pt-5 pb-4', flush && 'border-b')}
                >
                    {title && (
                        <CardTitle className="text-base">{title}</CardTitle>
                    )}
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                    {action && <CardAction>{action}</CardAction>}
                </CardHeader>
            )}
            {children !== undefined && (
                <CardContent
                    className={cn(
                        flush ? 'px-0' : 'px-5 pb-5',
                        !title && !action && !flush && 'pt-5',
                    )}
                >
                    {children}
                </CardContent>
            )}
            {footer && (
                <CardFooter className="border-t px-5 py-3">{footer}</CardFooter>
            )}
        </Card>
    );
}

/** Anahtar / değer listesi (yan kartlarda müşteri, adres, kargo bilgisi). */
export function InfoList({
    items,
    className,
}: {
    items: { label: ReactNode; value: ReactNode }[];
    className?: string;
}) {
    return (
        <dl className={cn('flex flex-col gap-3 text-sm', className)}>
            {items.map((item, i) => (
                <div key={i} className="flex flex-col gap-0.5">
                    <dt className="text-xs text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd className="font-medium break-words">
                        {item.value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** Tutar özeti satırları (ara toplam, indirim, KDV, kargo, toplam). */
export function TotalsList({
    rows,
    total,
}: {
    rows: { label: ReactNode; value: ReactNode; muted?: boolean }[];
    total: { label: ReactNode; value: ReactNode };
}) {
    return (
        <dl className="flex flex-col gap-2 text-sm">
            {rows.map((row, i) => (
                <div
                    key={i}
                    className="flex items-center justify-between gap-4"
                >
                    <dt className="text-muted-foreground">{row.label}</dt>
                    <dd
                        className={cn(
                            'tabular-nums',
                            row.muted && 'text-muted-foreground',
                        )}
                    >
                        {row.value}
                    </dd>
                </div>
            ))}
            <div className="mt-1 flex items-center justify-between gap-4 border-t pt-3 text-base font-semibold">
                <dt>{total.label}</dt>
                <dd className="tabular-nums">{total.value}</dd>
            </div>
        </dl>
    );
}

/** Liste sayfası: solda filtre paneli (isteğe bağlı), sağda tablo kartı. */
export function ListLayout({
    filters,
    children,
}: {
    filters?: ReactNode;
    children: ReactNode;
}) {
    if (!filters) {
        return <div className="flex min-w-0 flex-col gap-6">{children}</div>;
    }

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside className="min-w-0">{filters}</aside>
            <div className="flex min-w-0 flex-col gap-6">{children}</div>
        </div>
    );
}

/** Filtre paneli kartı ve grupları. */
export function FilterPanel({
    children,
    footer,
}: {
    children: ReactNode;
    footer?: ReactNode;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b px-5 py-4">
                <CardTitle className="text-base">{t('Filtreler')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col divide-y px-0">
                {children}
            </CardContent>
            {footer && (
                <CardFooter className="gap-2 border-t px-5 py-3">
                    {footer}
                </CardFooter>
            )}
        </Card>
    );
}

export function FilterGroup({
    title,
    children,
}: {
    title: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2.5 px-5 py-4">
            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </div>
            {children}
        </div>
    );
}

/**
 * Tablo kartı: üstte araç çubuğu (arama, hızlı filtreler), ortada kenara
 * dayalı tablo, altta sayfalama.
 */
export function TableCard({
    toolbar,
    footer,
    children,
}: {
    toolbar?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            {toolbar && (
                <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center">
                    {toolbar}
                </div>
            )}
            <div className="min-w-0 [&_td:first-child]:pl-4 [&_td:last-child]:pr-4 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-4 [&_th:last-child]:pr-4">
                {children}
            </div>
            {footer && <div className="border-t px-4 py-3">{footer}</div>}
        </Card>
    );
}
