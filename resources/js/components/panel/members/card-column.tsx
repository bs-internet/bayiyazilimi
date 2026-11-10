import type { ReactNode } from 'react';

/** Cari kart sütunu: küçük başlık + etiket/değer satırları. */
export function CardColumn({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 p-5">
            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </div>
            <dl className="flex flex-col gap-2 text-sm">{children}</dl>
        </div>
    );
}
