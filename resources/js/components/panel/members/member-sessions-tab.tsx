import { RecordsCard } from '@/components/panel/members/records-card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { UserSessionLog } from '@/types';

/** Oturumlar sekmesi: son giriş/çıkış kayıtları. */
export function MemberSessionsTab({
    sessions,
}: {
    sessions: UserSessionLog[];
}) {
    return (
        <RecordsCard
            title={t('Oturumlar')}
            description={t('Üyenin son 20 giriş ve çıkış kaydı.')}
            empty={t('Oturum kaydı yok.')}
            count={sessions.length}
            total={sessions.length}
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Cihaz')}</TableHead>
                        <TableHead>{t('IP')}</TableHead>
                        <TableHead>{t('Giriş')}</TableHead>
                        <TableHead>{t('Çıkış')}</TableHead>
                        <TableHead>{t('Durum')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sessions.map((session) => (
                        <TableRow key={session.id}>
                            <TableCell>
                                <div className="text-sm font-medium">
                                    {session.device} · {session.browser}
                                </div>
                                <div className="max-w-64 truncate text-xs text-muted-foreground">
                                    {session.platform}
                                </div>
                            </TableCell>
                            <TableCell className="text-sm tabular-nums">
                                {session.ip_address ?? '-'}
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                {formatDateTime(session.login_at)}
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                {formatDateTime(session.logout_at)}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant={
                                        session.logout_at
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {session.logout_at
                                        ? t('Kapandı')
                                        : t('Açık olabilir')}
                                </Badge>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </RecordsCard>
    );
}
