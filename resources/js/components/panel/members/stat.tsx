import type { ReactNode } from 'react';

export function Stat({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5 bg-card px-5 py-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-lg font-semibold tabular-nums">
                {children}
            </span>
        </div>
    );
}
