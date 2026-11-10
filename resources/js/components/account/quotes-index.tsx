import { Link } from '@inertiajs/react';
import { FileText, ListChecks } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { QuoteStatusBadge } from '@/components/account/status-badge';
import { PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
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

export type QuoteRow = {
    id: string;
    number: string;
    status: string;
    status_label: string;
    from_store: boolean;
    needs_action: boolean;
    items_count: number;
    grand_total: string | null;
    valid_until: string | null;
    created_at: string | null;
};

export type QuotesIndexProps = {
    quotes: Paginated<QuoteRow>;
};

export function QuotesIndexContent({
    area,
    quotes,
    embedded = false,
}: QuotesIndexProps & {
    area: AccountArea;
    /** Bölüm düzeni içinde (bayi › Teklifler): küçük başlık, konum izi yok. */
    embedded?: boolean;
}) {
    const routes = accountRoutes(area);

    return (
        <>
            {embedded ? (
                <PageTitle
                    compact
                    title={t('Tekliflerim')}
                    description={t(
                        'Teklif talepleriniz ve size özel hazırlanan teklifler. Gelen teklifi onaylayabilir, revize isteyebilir ya da reddedebilirsiniz.',
                    )}
                />
            ) : (
                <AccountTitle
                    area={area}
                    title={t('Tekliflerim')}
                    description={t(
                        'Teklif talepleriniz ve size özel hazırlanan teklifler. Gelen teklifi onaylayabilir, revize isteyebilir ya da reddedebilirsiniz.',
                    )}
                />
            )}

            <TableCard
                footer={
                    quotes.last_page > 1 ? (
                        <DataPagination
                            paginator={quotes}
                            label={t('teklif')}
                        />
                    ) : undefined
                }
            >
                {quotes.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText />
                            </EmptyMedia>
                            <EmptyTitle>{t('Henüz teklifiniz yok')}</EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Ürün sayfalarındaki “Teklif Al” butonuyla teklif listenizi oluşturabilirsiniz.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild variant="secondary">
                                <a href={routes.quoteList.index().url}>
                                    <ListChecks data-icon="inline-start" />
                                    {t('Teklif listem')}
                                </a>
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Teklif No')}</TableHead>
                                <TableHead>{t('Tarih')}</TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                                <TableHead>{t('Geçerlilik')}</TableHead>
                                <TableHead className="text-right">
                                    {t('Tutar')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {quotes.data.map((quote) => (
                                <TableRow key={quote.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={routes.quotes.show(quote.id)}
                                            className="hover:underline"
                                        >
                                            {quote.number}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {t(':count ürün', {
                                                count: quote.items_count,
                                            })}
                                            {quote.from_store
                                                ? t(' · Size özel')
                                                : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(quote.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <QuoteStatusBadge
                                                status={quote.status}
                                                label={quote.status_label}
                                            />
                                            {quote.needs_action ? (
                                                <Link
                                                    href={routes.quotes.show(
                                                        quote.id,
                                                    )}
                                                    className="text-xs font-medium text-primary hover:underline"
                                                >
                                                    {t('İşlem bekliyor')}
                                                </Link>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {formatDate(quote.valid_until)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {quote.grand_total ?? '-'}
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
