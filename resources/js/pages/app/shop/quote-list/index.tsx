import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList, Send, ShoppingBasket } from 'lucide-react';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import { DealerBasketTable } from '@/components/dealer-shop/basket-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import cart from '@/routes/app/shop/cart';
import quoteList from '@/routes/app/shop/quote-list';
import type { BasketLine } from '@/types';

type Props = {
    lines: BasketLine[];
    prefill: {
        customer_name: string | null;
        email: string | null;
        company: string | null;
        phone: string | null;
        city: string | null;
    };
};

/** Bayi paneli › Teklifler › Teklif listem: liste + teklif talebi. */
export default function AppQuoteList({ lines, prefill }: Props) {
    const count = lines.reduce((sum, l) => sum + l.quantity, 0);

    return (
        <>
            <Head title={t('Teklif listem')} />
            <Page embedded>
                <PageTitle
                    compact
                    title={t('Teklif listem')}
                    meta={
                        lines.length > 0 ? (
                            <>
                                <span>
                                    {t(':lines_length çeşit', {
                                        lines_length: lines.length,
                                    })}
                                </span>
                                <MetaDot />
                                <span>{t(':count adet', { count })}</span>
                            </>
                        ) : undefined
                    }
                    description={t(
                        "Listenizdeki ürünler için size özel fiyat teklifi hazırlanır; gelen teklifleri Tekliflerim'den onaylarsınız.",
                    )}
                />

                {lines.length === 0 ? (
                    <Empty className="border bg-card">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ClipboardList />
                            </EmptyMedia>
                            <EmptyTitle>{t('Teklif listeniz boş')}</EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Sepetinizdeki ürünleri “Teklif listesine taşı” ile buraya alabilirsiniz.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent className="flex-row flex-wrap justify-center">
                            <Button variant="outline" asChild>
                                <Link href={cart.index()}>
                                    <ShoppingBasket data-icon="inline-start" />
                                    {t('Sipariş ver')}
                                </Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <EntityLayout stackUntil="2xl">
                        <EntityMain>
                            <DealerBasketTable
                                lines={lines}
                                updateForm={quoteList.update.form()}
                                removeForm={(id) => quoteList.remove.form(id)}
                                showPrices={false}
                            />
                        </EntityMain>
                        <EntitySidebar>
                            <SectionCard title={t('Teklif talebi')}>
                                <Form
                                    {...quoteList.submit.form()}
                                    className="flex flex-col gap-5"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            {/* İletişim bilgileri hesaptan alınır. */}
                                            <div className="rounded-lg bg-muted px-3 py-2.5 text-sm">
                                                <div className="font-medium">
                                                    {prefill.customer_name}
                                                    {prefill.company
                                                        ? ` · ${prefill.company}`
                                                        : ''}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {prefill.email}
                                                    {prefill.phone
                                                        ? ` · ${prefill.phone}`
                                                        : ''}
                                                </div>
                                            </div>
                                            <Field
                                                data-invalid={Boolean(
                                                    errors.customer_note,
                                                )}
                                            >
                                                <FieldLabel htmlFor="ql-note">
                                                    {t('Notunuz')}
                                                </FieldLabel>
                                                <Textarea
                                                    id="ql-note"
                                                    name="customer_note"
                                                    rows={4}
                                                    placeholder={t(
                                                        'Örn. teslim yeri, istenen tarih, alternatif ürün…',
                                                    )}
                                                />
                                                <InputError
                                                    message={
                                                        errors.customer_note
                                                    }
                                                />
                                            </Field>
                                            <Button
                                                type="submit"
                                                size="lg"
                                                disabled={processing}
                                            >
                                                <Send data-icon="inline-start" />
                                                {t('Teklif iste')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </SectionCard>
                        </EntitySidebar>
                    </EntityLayout>
                )}
            </Page>
        </>
    );
}

AppQuoteList.layout = {
    breadcrumbs: [{ title: t('Teklif listem'), href: quoteList.index() }],
};
