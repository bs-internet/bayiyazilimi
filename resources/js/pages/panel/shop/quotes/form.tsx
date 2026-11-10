import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink, Package } from 'lucide-react';
import { useMemo } from 'react';

import { QuoteStatusBadge } from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    Page,
    PageTitle,
} from '@/components/admin/page';
import { LineItemsCard } from '@/components/panel/quotes/line-items-card';
import {
    computeTotals,
    lineFromItem,
    lineFromProduct,
} from '@/components/panel/quotes/lines';
import type { PickerProduct } from '@/components/panel/quotes/lines';
import { ProductFinder } from '@/components/panel/quotes/product-finder';
import { QuoteSidebar } from '@/components/panel/quotes/quote-sidebar';
import { SummarySection } from '@/components/panel/quotes/summary-section';
import type { QuoteData, QuoteFormData } from '@/components/panel/quotes/types';
import { ValiditySection } from '@/components/panel/quotes/validity-section';
import type { SearchSelectOption } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toInput } from '@/lib/decimal';
import type { DecimalInput } from '@/lib/decimal';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { show as orderShow } from '@/routes/panel/shop/orders';
import { index, store, update } from '@/routes/panel/shop/quotes';
import type { ShopOption } from '@/types';

type Props = {
    quote: QuoteData | null;
    defaultCustomer: string | null;
    customers: SearchSelectOption[];
    currencies: ShopOption[];
    defaultCurrency: string;
    categories: SearchSelectOption[];
    brands: SearchSelectOption[];
    units: ShopOption[];
};

export default function QuoteForm({
    quote,
    defaultCustomer,
    customers,
    currencies,
    defaultCurrency,
    categories,
    brands,
    units,
}: Props) {
    const editable = quote?.editable ?? true;
    const adminQuote = quote === null || quote.source === 'admin';
    const form = useForm<QuoteFormData>({
        user_id: quote?.user_id ?? defaultCustomer ?? '',
        currency_code: quote?.currency_code ?? defaultCurrency,
        admin_note: quote?.admin_note ?? '',
        shipping_total: quote ? toInput(quote.shipping_total, 2) : '0',
        valid_from: quote?.valid_from ?? '',
        valid_until: quote?.valid_until ?? '',
        items: (quote?.items ?? []).map(lineFromItem),
        send: false,
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;

    const money = (value: DecimalInput) =>
        formatMoney(value, data.currency_code || 'TRY');

    const selected = useMemo(
        () => new Set(data.items.map((line) => line.product_id)),
        [data.items],
    );

    const totals = useMemo(
        () => computeTotals(data.items, data.shipping_total),
        [data.items, data.shipping_total],
    );

    const addLine = (product: PickerProduct) =>
        setData('items', [...data.items, lineFromProduct(product)]);

    const submit = (send: boolean) => {
        form.transform((d) => ({ ...d, send }));

        const options = { preserveScroll: true };

        if (quote) {
            form.put(update.url(quote.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const title = quote
        ? t('Teklif :number', { number: quote.number })
        : t('Yeni teklif');
    const sendLabel =
        quote?.status === 'sent'
            ? t('Kaydet ve yeniden gönder')
            : t('Kaydet ve gönder');

    return (
        <>
            <Head title={title} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Satış') },
                        { title: t('Teklifler'), href: index() },
                        { title: quote ? `#${quote.number}` : t('Yeni') },
                    ]}
                    title={title}
                    badges={
                        quote ? (
                            <>
                                <QuoteStatusBadge
                                    status={quote.status}
                                    label={quote.status_label}
                                />
                                <Badge variant="outline">
                                    {quote.source === 'admin'
                                        ? t('Yönetici teklifi')
                                        : t('Müşteri talebi')}
                                </Badge>
                                {quote.expired &&
                                ['sent', 'approved'].includes(quote.status) ? (
                                    <Badge variant="destructive">
                                        {t('Süresi doldu')}
                                    </Badge>
                                ) : null}
                            </>
                        ) : undefined
                    }
                    description={
                        quote && !editable
                            ? t(
                                  'Bu teklif onaylandı, siparişe dönüştü ya da kapandı; içeriği değiştirilemez.',
                              )
                            : t(
                                  'Fiyatları girip "Kaydet ve gönder" deyin; müşteri e-postayla haberdar olur ve kendi alanında onaylar, revize ister ya da reddeder.',
                              )
                    }
                    actions={
                        quote ? (
                            <>
                                {quote.order ? (
                                    <Button asChild variant="outline">
                                        <Link href={orderShow(quote.order.id)}>
                                            <Package data-icon="inline-start" />
                                            {t('Sipariş :number', {
                                                number: quote.order.number,
                                            })}
                                        </Link>
                                    </Button>
                                ) : null}
                                {quote.status !== 'draft' &&
                                quote.public_url ? (
                                    <Button asChild variant="outline">
                                        <a
                                            href={quote.public_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink data-icon="inline-start" />
                                            {t('Müşterinin gördüğü')}
                                        </a>
                                    </Button>
                                ) : null}
                            </>
                        ) : undefined
                    }
                />

                <EntityLayout>
                    <EntityMain>
                        <ValiditySection
                            quote={quote}
                            adminQuote={adminQuote}
                            editable={editable}
                            customers={customers}
                            currencies={currencies}
                            defaultCurrency={defaultCurrency}
                            data={data}
                            setData={setData}
                            errors={errors}
                        />

                        {editable ? (
                            <ProductFinder
                                categories={categories}
                                brands={brands}
                                currency={data.currency_code}
                                money={money}
                                selected={selected}
                                onAdd={addLine}
                            />
                        ) : null}

                        <LineItemsCard
                            items={data.items}
                            setItems={(items) => setData('items', items)}
                            currencyCode={data.currency_code}
                            editable={editable}
                            unpriced={totals.unpriced}
                            units={units}
                            money={money}
                            errors={fieldErrors}
                        />

                        <SummarySection
                            quote={quote}
                            editable={editable}
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            totals={totals}
                            money={money}
                            sendLabel={sendLabel}
                            onSubmit={submit}
                        />
                    </EntityMain>

                    <QuoteSidebar quote={quote} />
                </EntityLayout>
            </Page>
        </>
    );
}

QuoteForm.layout = {
    breadcrumbs: [{ title: t('Teklifler'), href: index() }],
};
