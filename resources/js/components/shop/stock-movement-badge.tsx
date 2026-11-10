import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** Hareket türü → renkli rozet (tema token'ları ile). */
const TYPE_CLASS: Record<string, string> = {
    in: 'bg-primary/10 text-primary',
    return: 'bg-primary/10 text-primary',
    import: 'bg-primary/10 text-primary',
    transfer_in: 'bg-secondary text-secondary-foreground',
    transfer_out: 'bg-secondary text-secondary-foreground',
    out: 'bg-destructive/10 text-destructive',
    sale: 'bg-destructive/10 text-destructive',
    adjust: 'bg-muted text-foreground',
    lot_change: 'bg-secondary text-secondary-foreground',
    relocate: 'bg-secondary text-secondary-foreground',
};

export function StockMovementBadge({
    type,
    label,
}: {
    type: string;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-transparent',
                TYPE_CLASS[type] ?? 'bg-muted text-foreground',
            )}
        >
            {label}
        </Badge>
    );
}
