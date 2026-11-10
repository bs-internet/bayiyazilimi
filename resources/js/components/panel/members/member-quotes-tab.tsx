import { Link } from '@inertiajs/react';
import { QuoteStatusBadge } from '@/components/account/status-badge';
import { RecordsCard } from '@/components/panel/members/records-card';
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
import {
    index as quotesIndex,
    edit as quoteShow,
} from '@/routes/panel/shop/quotes';
import type { MemberOverview } from '@/types';

/** Teklifler sekmesi: son teklif talepleri tablosu. */
export function MemberQuotesTab({
    quotes,
    accountQuery,
}: {
    quotes: MemberOverview['quotes'];
    accountQuery: { query: { account: string } } | null;
}) {
    return (
        <RecordsCard
            title={t('Teklifler')}
            empty={t('Henüz teklif talebi yok.')}
            count={quotes.data.length}
            total={quotes.total}
            allHref={accountQuery && quotesIndex(accountQuery)}
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Teklif')}</TableHead>
                        <TableHead>{t('Tarih')}</TableHead>
                        <TableHead>{t('Durum')}</TableHead>
                        <TableHead className="text-right">
                            {t('Tutar')}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {quotes.data.map((quote) => (
                        <TableRow key={quote.id}>
                            <TableCell>
                                <Link
                                    href={quoteShow(quote.id)}
                                    className="font-medium hover:underline"
                                >
                                    #{quote.number}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    {t(':items_count ürün', {
                                        items_count: quote.items_count,
                                    })}
                                </div>
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                {formatDateTime(quote.created_at)}
                            </TableCell>
                            <TableCell>
                                <QuoteStatusBadge
                                    status={quote.status}
                                    label={quote.status_label}
                                />
                            </TableCell>
                            <TableCell className="text-right font-medium tabular-nums">
                                {quote.grand_total ?? '—'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </RecordsCard>
    );
}
