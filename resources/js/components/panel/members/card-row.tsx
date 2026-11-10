import type { ReactNode } from 'react';

/** Kart sütunu satırı: etiket + değer (boşsa "—"). */
export function Row({
    label,
    children,
}: {
    label: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="grid grid-cols-[9rem_1fr] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 font-medium break-words">
                {children || children === 0 ? children : '—'}
            </dd>
        </div>
    );
}
