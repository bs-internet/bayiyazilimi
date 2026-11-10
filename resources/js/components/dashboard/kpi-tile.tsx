import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * Başlangıç ekranlarındaki KPI kutusu: renkli zeminli ikon, büyük değer,
 * küçük etiket ve isteğe bağlı önceki döneme göre değişim (%).
 */

export type KpiTone = 'primary' | 'sky' | 'amber' | 'green' | 'red';

const toneClasses: Record<KpiTone, string> = {
    primary: 'bg-primary/10 text-primary',
    sky: 'bg-chart-2/15 text-chart-2',
    amber: 'bg-chart-3/25 text-foreground',
    green: 'bg-chart-4/15 text-chart-4',
    red: 'bg-chart-5/15 text-chart-5',
};

export function KpiDelta({ value }: { value: number | null | undefined }) {
    if (value === null || value === undefined) {
        return null;
    }

    const Icon = value > 0 ? ArrowUpRight : value < 0 ? ArrowDownRight : Minus;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-medium tabular-nums',
                value > 0 && 'bg-chart-4/15 text-chart-4',
                value < 0 && 'bg-chart-5/15 text-chart-5',
                value === 0 && 'bg-muted text-muted-foreground',
            )}
        >
            <Icon className="size-3" />
            {`${value > 0 ? '+' : ''}${value.toLocaleString('tr-TR')}%`}
        </span>
    );
}

export function KpiTile({
    icon: Icon,
    label,
    value,
    hint,
    delta,
    tone = 'primary',
    href,
    external = false,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    delta?: number | null;
    tone?: KpiTone;
    href?: string;
    /** Inertia dışı (vitrin) bağlantı: tam sayfa yükleme. */
    external?: boolean;
}) {
    const body = (
        <Card
            className={cn(
                'h-full gap-0 py-0',
                href && 'transition-shadow hover:shadow-md',
            )}
        >
            <CardContent className="flex items-start gap-4 p-5">
                <div
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-xl',
                        toneClasses[tone],
                    )}
                >
                    <Icon className="size-6" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                        <span className="truncate text-sm text-muted-foreground">
                            {label}
                        </span>
                        <KpiDelta value={delta} />
                    </div>
                    <div className="mt-1 truncate text-2xl font-semibold tracking-tight tabular-nums">
                        {value}
                    </div>
                    {hint && (
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            {hint}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );

    if (!href) {
        return body;
    }

    return external ? (
        <a href={href} className="block rounded-xl">
            {body}
        </a>
    ) : (
        <Link href={href} className="block rounded-xl">
            {body}
        </Link>
    );
}
