import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { LedgerBalance, LedgerData, LedgerRow } from '@/types';

export function BalanceText({
    balance,
    className,
}: {
    balance: LedgerBalance;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'tabular-nums',
                balance.side === 'debit' && 'text-destructive',
                balance.side === 'credit' && 'text-emerald-600',
                className,
            )}
        >
            {balance.formatted}
            {balance.side !== 'zero' && (
                <span className="ml-1 text-xs text-muted-foreground">
                    {balance.side === 'debit' ? '(B)' : '(A)'}
                </span>
            )}
        </span>
    );
}

/**
 * Cari ekstre: tarih filtresi, devir, hareketler (yeniden eskiye, yürüyen
 * bakiye) ve dönem toplamları. Panel müşteri detayı ve Cari Hesabım ortak.
 */
export function LedgerStatement({
    ledger,
    orderLink,
    rowAction,
    toolbar,
    paramPrefix = '',
}: {
    ledger: LedgerData;
    /** Sipariş numarasının bağlantısı (alanına göre). */
    orderLink: (order: NonNullable<LedgerRow['order']>) => ReactNode;
    /** Satır işlemi (panel: kayıt iptali). */
    rowAction?: (row: LedgerRow) => ReactNode;
    toolbar?: ReactNode;
    /** Sorgu parametresi öneki (panel müşteri detayında `ledger_`). */
    paramPrefix?: string;
}) {
    const { statement, filters } = ledger;
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const apply = (values: { from: string; to: string }) => {
        router.get(
            window.location.pathname,
            {
                ...Object.fromEntries(
                    new URLSearchParams(window.location.search),
                ),
                [`${paramPrefix}from`]: values.from || undefined,
                [`${paramPrefix}to`]: values.to || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex flex-col gap-3 border-b px-4 py-3 lg:flex-row lg:items-end lg:justify-between">
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({ from, to });
                    }}
                >
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        {t('Başlangıç')}
                        <Input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="h-9 w-40"
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        {t('Bitiş')}
                        <Input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="h-9 w-40"
                        />
                    </label>
                    <Button type="submit" variant="secondary" size="sm">
                        <Search />
                        {t('Filtrele')}
                    </Button>
                    {(filters.from || filters.to) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setFrom('');
                                setTo('');
                                apply({ from: '', to: '' });
                            }}
                        >
                            <X />
                            {t('Temizle')}
                        </Button>
                    )}
                </form>
                {toolbar && (
                    <div className="flex flex-wrap items-center gap-2">
                        {toolbar}
                    </div>
                )}
            </div>

            {statement.rows.length === 0 ? (
                <Empty className="py-10">
                    <EmptyHeader>
                        <EmptyTitle className="text-sm">
                            {t('Bu dönemde cari hareket yok.')}
                        </EmptyTitle>
                        <EmptyDescription>
                            {t(
                                'Siparişler hazırlanmaya alındığında borç, ödemeler alındığında alacak olarak buraya yazılır.',
                            )}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className="overflow-x-auto [&_td:first-child]:pl-4 [&_td:last-child]:pr-4 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-4 [&_th:last-child]:pr-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Tarih')}</TableHead>
                                <TableHead>{t('Açıklama')}</TableHead>
                                <TableHead>{t('Vade')}</TableHead>
                                <TableHead className="text-right">
                                    {t('Borç')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('Alacak')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('Bakiye')}
                                </TableHead>
                                {rowAction && (
                                    <TableHead className="w-10">
                                        <span className="sr-only">
                                            {t('İşlem')}
                                        </span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {statement.rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    className={cn(
                                        (row.reversed || row.is_reversal) &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <TableCell className="whitespace-nowrap tabular-nums">
                                        {formatDate(row.date)}
                                    </TableCell>
                                    <TableCell className="min-w-56">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <Badge variant="outline">
                                                {row.type_label}
                                            </Badge>
                                            {row.is_reversal && (
                                                <Badge variant="secondary">
                                                    {t('İptal')}
                                                </Badge>
                                            )}
                                            {row.reversed && (
                                                <Badge variant="secondary">
                                                    {t('İptal edildi')}
                                                </Badge>
                                            )}
                                            {row.origin && (
                                                <Badge variant="secondary">
                                                    {row.origin}
                                                </Badge>
                                            )}
                                        </div>
                                        <div
                                            className={cn(
                                                'mt-1 text-sm',
                                                row.reversed && 'line-through',
                                            )}
                                        >
                                            {row.description}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.order && orderLink(row.order)}
                                            {row.original && (
                                                <span>
                                                    {row.order ? ' · ' : ''}
                                                    {row.original}
                                                </span>
                                            )}
                                            {row.user && (
                                                <span> · {row.user}</span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap tabular-nums">
                                        {row.due_date
                                            ? formatDate(row.due_date)
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {row.debit ?? ''}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {row.credit ?? ''}
                                    </TableCell>
                                    <TableCell className="text-right font-medium whitespace-nowrap">
                                        <BalanceText balance={row.balance} />
                                    </TableCell>
                                    {rowAction && (
                                        <TableCell>{rowAction(row)}</TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <div className="grid gap-2 border-t px-4 py-3 text-sm sm:grid-cols-4">
                <div>
                    <span className="text-muted-foreground">
                        {t('Devir:')}{' '}
                    </span>
                    <BalanceText balance={statement.opening} />
                </div>
                <div>
                    <span className="text-muted-foreground">{t('Borç:')} </span>
                    <span className="tabular-nums">
                        {statement.debit_total}
                    </span>
                </div>
                <div>
                    <span className="text-muted-foreground">
                        {t('Alacak:')}{' '}
                    </span>
                    <span className="tabular-nums">
                        {statement.credit_total}
                    </span>
                </div>
                <div className="font-semibold">
                    <span className="text-muted-foreground">
                        {t('Bakiye:')}{' '}
                    </span>
                    <BalanceText balance={statement.closing} />
                </div>
            </div>
        </Card>
    );
}
