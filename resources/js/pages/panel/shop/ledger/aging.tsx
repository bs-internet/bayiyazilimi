import { Head, Link } from '@inertiajs/react';
import { CalendarClock, FileSpreadsheet } from 'lucide-react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-permissions';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/panel';
import { show as memberShow } from '@/routes/panel/members';
import { aging, agingExport } from '@/routes/panel/shop/ledger';

type BucketKey = 'not_due' | 'd30' | 'd60' | 'd90' | 'd90p';

type Row = {
    account_id: string;
    title: string;
    code: string;
    member_id: string | null;
    formatted: Record<BucketKey | 'total', string>;
} & Record<BucketKey | 'total', number>;

type Props = {
    buckets: Record<BucketKey, string>;
    rows: Row[];
    totals: Record<BucketKey | 'total', string>;
};

export default function Aging({ buckets, rows, totals }: Props) {
    const canExport = useCan('panel.shop.ledger.aging-export');
    const keys = Object.keys(buckets) as BucketKey[];

    return (
        <>
            <Head title={t('Geciken Ödemeler')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Geciken Ödemeler')}
                    meta={t(':rows_length hesap · toplam :total', {
                        rows_length: rows.length,
                        total: totals.total,
                    })}
                    description={t(
                        'Açık (tahsilatla kapanmamış) borçların vadesine göre dağılımı. Vadesi olmayan borçlar (dekont, açılış) işlem tarihinden yaşlandırılır.',
                    )}
                    actions={
                        canExport ? (
                            <Button variant="outline" asChild>
                                <a href={agingExport.url()}>
                                    <FileSpreadsheet data-icon="inline-start" />
                                    {t('Excel')}
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                <TableCard>
                    {rows.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <CalendarClock />
                                </EmptyMedia>
                                <EmptyTitle>{t('Açık borç yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('Tüm cari borçlar tahsilatla kapanmış.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Hesap')}</TableHead>
                                        {keys.map((k) => (
                                            <TableHead
                                                key={k}
                                                className="text-right"
                                            >
                                                {buckets[k]}
                                            </TableHead>
                                        ))}
                                        <TableHead className="text-right">
                                            {t('Toplam')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((r) => (
                                        <TableRow key={r.account_id}>
                                            <TableCell>
                                                {r.member_id ? (
                                                    <Link
                                                        href={memberShow(
                                                            r.member_id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {r.title}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">
                                                        {r.title}
                                                    </span>
                                                )}
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {r.code}
                                                </div>
                                            </TableCell>
                                            {keys.map((k) => (
                                                <TableCell
                                                    key={k}
                                                    className={cn(
                                                        'text-right whitespace-nowrap tabular-nums',
                                                        r[k] === 0 &&
                                                            'text-muted-foreground',
                                                        k === 'd90p' &&
                                                            r[k] > 0 &&
                                                            'font-semibold text-destructive',
                                                    )}
                                                >
                                                    {r[k] === 0
                                                        ? '—'
                                                        : r.formatted[k]}
                                                </TableCell>
                                            ))}
                                            <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                                {r.formatted.total}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell className="font-semibold">
                                            {t('Toplam')}
                                        </TableCell>
                                        {keys.map((k) => (
                                            <TableCell
                                                key={k}
                                                className="text-right font-semibold whitespace-nowrap tabular-nums"
                                            >
                                                {totals[k]}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                            {totals.total}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

Aging.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Geciken Ödemeler'), href: aging() },
    ],
};
