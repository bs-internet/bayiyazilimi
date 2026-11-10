import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Panel ve üye alanı sayfa başlığı: gri zeminde başlık + açıklama solda,
 * eylem düğmeleri sağda (mobilde alt alta). Kartların üstünde durur.
 */
export function PageHeader({
    title,
    description,
    actions,
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                <h1 className="truncate text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
