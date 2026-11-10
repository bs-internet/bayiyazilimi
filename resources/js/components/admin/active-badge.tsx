import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Aktif / pasif durum rozeti (tema token'ları ile renkli). */
export function ActiveBadge({
    active,
    activeLabel = t('Aktif'),
    passiveLabel = t('Pasif'),
    className,
}: {
    active: boolean;
    activeLabel?: string;
    passiveLabel?: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-transparent',
                active
                    ? 'bg-primary/10 text-primary'
                    : 'bg-muted text-muted-foreground',
                className,
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-1.5 rounded-full',
                    active ? 'bg-primary' : 'bg-muted-foreground/60',
                )}
            />
            {active ? activeLabel : passiveLabel}
        </Badge>
    );
}
