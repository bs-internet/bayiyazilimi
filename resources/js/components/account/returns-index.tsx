import { Link } from '@inertiajs/react';
import { Plus, Undo2 } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { ReturnStatusBadge } from '@/components/account/status-badge';
import { SectionCard, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types';

export type ReturnRow = {
    id: string;
    number: string;
    order_number: string | null;
    status: string;
    status_label: string;
    reason_label: string;
    items_count: number;
    created_at: string | null;
};

export type ReturnsIndexProps = {
    returns: Paginated<ReturnRow>;
    eligibleOrders: {
        id: string;
        number: string;
        created_at: string | null;
        grand_total: string;
    }[];
    returnDays: number;
};

export function ReturnsIndexContent({
    area,
    returns,
    eligibleOrders,
    returnDays,
}: ReturnsIndexProps & { area: AccountArea }) {
    const routes = accountRoutes(area);

    return (
        <>
            <AccountTitle
                area={area}
                title={t('İadelerim')}
                description={t(
                    'Kargoya verilmiş siparişleriniz için :return_days gün içinde iade talebi oluşturabilirsiniz.',
                    { return_days: returnDays },
                )}
            />

            {eligibleOrders.length > 0 && (
                <SectionCard
                    title={t('İade talebi oluştur')}
                    description={t('İade süresi içindeki siparişleriniz.')}
                >
                    <div className="flex flex-wrap gap-2">
                        {eligibleOrders.map((o) => (
                            <Button
                                key={o.id}
                                asChild
                                variant="outline"
                                size="sm"
                            >
                                <Link href={routes.returns.create(o.id)}>
                                    <Plus data-icon="inline-start" />
                                    {o.number} · {formatDate(o.created_at)} ·{' '}
                                    {o.grand_total}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </SectionCard>
            )}

            <TableCard
                footer={
                    returns.last_page > 1 ? (
                        <DataPagination paginator={returns} label={t('iade')} />
                    ) : undefined
                }
            >
                {returns.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Undo2 />
                            </EmptyMedia>
                            <EmptyTitle>
                                {t('Henüz iade talebiniz yok')}
                            </EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Oluşturduğunuz iade talepleri ve durumları burada listelenir.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('İade No')}</TableHead>
                                <TableHead>{t('Tarih')}</TableHead>
                                <TableHead>{t('Sipariş')}</TableHead>
                                <TableHead>{t('Neden')}</TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {returns.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={routes.returns.show(r.id)}
                                            className="hover:underline"
                                        >
                                            {r.number}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {t(':count kalem', {
                                                count: r.items_count,
                                            })}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(r.created_at)}
                                    </TableCell>
                                    <TableCell>{r.order_number}</TableCell>
                                    <TableCell>{r.reason_label}</TableCell>
                                    <TableCell>
                                        <ReturnStatusBadge
                                            status={r.status}
                                            label={r.status_label}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </TableCard>
        </>
    );
}
