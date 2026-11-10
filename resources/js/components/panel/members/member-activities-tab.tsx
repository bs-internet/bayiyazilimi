import { Activity } from 'lucide-react';
import { SectionCard } from '@/components/admin/page';
import { Badge } from '@/components/ui/badge';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { Activity as ActivityItem } from '@/types';

function eventLabel(event: string | null): string {
    switch (event) {
        case 'created':
            return t('Oluşturuldu');
        case 'updated':
            return t('Güncellendi');
        case 'deleted':
            return 'Silindi';
        case 'session_revoked':
            return t('Oturum kapatıldı');
        default:
            return event ?? t('Kayıt');
    }
}

/** Etkinlik sekmesi: üyeyle ilişkili son denetim kayıtları. */
export function MemberActivitiesTab({
    activities,
}: {
    activities: ActivityItem[];
}) {
    return (
        <SectionCard
            title={t('Etkinlik')}
            description={t('Üyeyle ilişkili son 20 denetim kaydı.')}
            flush
        >
            {activities.length === 0 ? (
                <Empty className="py-10">
                    <EmptyHeader>
                        <EmptyTitle className="text-sm">
                            {t('Etkinlik kaydı yok')}
                        </EmptyTitle>
                        <EmptyDescription>
                            {t(
                                'Bu üye için henüz kayıtlı etkinlik bulunmuyor.',
                            )}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <ol className="flex flex-col divide-y">
                    {activities.map((activity) => (
                        <li key={activity.id} className="flex gap-3 px-5 py-3">
                            <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Activity className="size-3.5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                    {activity.description ??
                                        eventLabel(activity.event)}
                                    <Badge variant="outline">
                                        {eventLabel(activity.event)}
                                    </Badge>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatDateTime(activity.created_at)} ·{' '}
                                    {activity.subject_label ?? '-'} ·{' '}
                                    {activity.causer?.name ?? '-'}
                                </div>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </SectionCard>
    );
}
