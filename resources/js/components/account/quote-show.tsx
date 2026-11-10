import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import { flushTableClass } from '@/components/account/parts';
import type {
    QuoteActions,
    QuoteHistoryEntry,
} from '@/components/account/quote-actions';
import {
    QuoteActionButtons,
    QuoteHistoryList,
} from '@/components/account/quote-actions';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { QuoteStatusBadge } from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    InfoList,
    MetaDot,
    SectionCard,
    TotalsList,
} from '@/components/admin/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

export type QuoteShowData = {
    id: string;
    number: string;
    status: string;
    status_label: string;
    from_store: boolean;
    priced: boolean;
    customer_note: string | null;
    admin_note: string | null;
    valid_from: string | null;
    valid_until: string | null;
    expired: boolean;
    not_started: boolean;
    subtotal: string | null;
    tax_total: string | null;
    shipping_total: string | null;
    grand_total: string | null;
    created_at: string | null;
    items: {
        id: string;
        name: string;
        sku: string | null;
        slug: string | null;
        image: string | null;
        quantity: number;
        unit: string | null;
        unit_price: string | null;
        tax_rate: string;
        line_total: string | null;
    }[];
    history: QuoteHistoryEntry[];
    order: { number: string; url: string } | null;
    actions: QuoteActions;
};

export type QuoteShowProps = {
    quote: QuoteShowData;
};

export function QuoteShowContent({
    area,
    quote,
    embedded = false,
}: QuoteShowProps & {
    area: AccountArea;
    /** Bölüm düzeni içinde (bayi › Teklifler): küçük başlık, dar alanda tek sütun. */
    embedded?: boolean;
}) {
    const routes = accountRoutes(area);
    const priced = quote.priced;

    return (
        <>
            <AccountTitle
                area={area}
                compact={embedded}
                title={t('Teklif :number', { number: quote.number })}
                parent={{
                    title: t('Tekliflerim'),
                    href: routes.quotes.index(),
                }}
                badges={
                    <>
                        <QuoteStatusBadge
                            status={quote.status}
                            label={quote.status_label}
                        />
                        {quote.from_store ? (
                            <Badge variant="outline">
                                {t('Size özel teklif')}
                            </Badge>
                        ) : null}
                    </>
                }
                meta={
                    <>
                        <span>{formatDateTime(quote.created_at)}</span>
                        <MetaDot />
                        <span>
                            {t(':count kalem', { count: quote.items.length })}
                        </span>
                        {priced ? (
                            <>
                                <MetaDot />
                                <span>
                                    {t('Toplam :grand_total', {
                                        grand_total: quote.grand_total,
                                    })}
                                </span>
                            </>
                        ) : null}
                    </>
                }
                actions={
                    quote.order ? (
                        <Button asChild variant="outline">
                            <Link href={quote.order.url}>
                                <Package data-icon="inline-start" />
                                {t('Sipariş :number', {
                                    number: quote.order.number,
                                })}
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <QuoteNotice quote={quote} />

            <EntityLayout stackUntil={embedded ? '2xl' : 'lg'}>
                <EntityMain>
                    <SectionCard
                        title={t('Ürünler')}
                        description={
                            priced
                                ? undefined
                                : t(
                                      'Teklifiniz hazırlandığında fiyatlar burada görünür ve size e-postayla haber verilir.',
                                  )
                        }
                        flush
                        footer={
                            priced ? (
                                <div className="ml-auto w-full max-w-sm">
                                    <TotalsList
                                        rows={[
                                            {
                                                label: t(
                                                    'Ara toplam (KDV hariç)',
                                                ),
                                                value: quote.subtotal,
                                            },
                                            {
                                                label: t('KDV'),
                                                value: quote.tax_total,
                                            },
                                            {
                                                label: t('Kargo'),
                                                value: quote.shipping_total,
                                            },
                                        ]}
                                        total={{
                                            label: t('Genel toplam'),
                                            value: quote.grand_total,
                                        }}
                                    />
                                </div>
                            ) : undefined
                        }
                    >
                        <div className={flushTableClass}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Ürün')}</TableHead>
                                        <TableHead className="text-center">
                                            {t('Adet')}
                                        </TableHead>
                                        {priced ? (
                                            <>
                                                <TableHead className="text-right">
                                                    {t('Birim (KDV hariç)')}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t('Tutar (KDV dahil)')}
                                                </TableHead>
                                            </>
                                        ) : null}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {quote.items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {item.name}
                                                </div>
                                                {item.sku ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('Kod: :sku', {
                                                            sku: item.sku,
                                                        })}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-center whitespace-nowrap tabular-nums">
                                                {item.quantity} {item.unit}
                                            </TableCell>
                                            {priced ? (
                                                <>
                                                    <TableCell className="text-right tabular-nums">
                                                        {item.unit_price ?? '-'}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {item.line_total ?? '-'}
                                                    </TableCell>
                                                </>
                                            ) : null}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </SectionCard>
                </EntityMain>

                <EntitySidebar>
                    <SectionCard title={t('Teklif')}>
                        <InfoList
                            items={[
                                {
                                    label: quote.from_store
                                        ? t('Teklif tarihi')
                                        : t('Talep tarihi'),
                                    value: formatDateTime(quote.created_at),
                                },
                                {
                                    label: t('Geçerlilik'),
                                    value:
                                        priced && quote.valid_until
                                            ? `${quote.valid_from ? `${formatDate(quote.valid_from)} – ` : ''}${formatDate(quote.valid_until)}${
                                                  quote.expired
                                                      ? t(' (süresi doldu)')
                                                      : ''
                                              }`
                                            : '—',
                                },
                            ]}
                        />
                    </SectionCard>

                    {quote.admin_note ? (
                        <SectionCard title={t('Notumuz')}>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {quote.admin_note}
                            </p>
                        </SectionCard>
                    ) : null}

                    <SectionCard title={t('Geçmiş')}>
                        <QuoteHistoryList history={quote.history} />
                    </SectionCard>

                    {quote.customer_note ? (
                        <SectionCard title={t('Talep notunuz')}>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {quote.customer_note}
                            </p>
                        </SectionCard>
                    ) : null}
                </EntitySidebar>
            </EntityLayout>
        </>
    );
}

/** Teklifin durumuna göre üstte açıklama + işlem düğmeleri. */
export function QuoteNotice({ quote }: { quote: QuoteShowData }) {
    const message = (() => {
        if (quote.actions.approve) {
            return t(
                'Teklifimiz hazır. İnceleyip onaylayabilir, değişiklik isteyebilir ya da reddedebilirsiniz.',
            );
        }

        if (quote.actions.checkout) {
            return t(
                'Teklifi onayladınız. Ödemeyi tamamladığınızda siparişiniz oluşur.',
            );
        }

        if (quote.not_started && quote.status === 'sent') {
            return t('Teklif :valid_from tarihinden itibaren geçerlidir.', {
                valid_from: formatDate(quote.valid_from),
            });
        }

        if (quote.expired && ['sent', 'approved'].includes(quote.status)) {
            return t(
                'Teklifin geçerlilik süresi doldu. Güncel fiyat için yeni talep oluşturabilirsiniz.',
            );
        }

        if (quote.status === 'pending') {
            return t(
                'Talebiniz alındı; fiyatlandırıldığında size e-postayla haber vereceğiz.',
            );
        }

        if (quote.status === 'revision') {
            return t(
                'Revize isteğiniz satış ekibimize iletildi; güncel teklif size e-postayla gönderilecek.',
            );
        }

        return null;
    })();

    if (message === null && !Object.values(quote.actions).some(Boolean)) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between">
            {message ? <p className="text-sm">{message}</p> : <span />}
            <QuoteActionButtons actions={quote.actions} className="shrink-0" />
        </div>
    );
}
