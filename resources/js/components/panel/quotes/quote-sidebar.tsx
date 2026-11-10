import { EntitySidebar, InfoList, SectionCard } from '@/components/admin/page';

import { t } from '@/lib/i18n';
import { CancelDialog } from './cancel-dialog';
import { HistoryCard } from './history-card';
import type { QuoteData } from './types';

/** Yan sütun: mevcut teklifte müşteri / not / geçmiş / iptal; yenide kısa rehber. */
export function QuoteSidebar({ quote }: { quote: QuoteData | null }) {
    return (
        <EntitySidebar>
            {quote ? (
                <>
                    <SectionCard title={t('Müşteri')}>
                        <InfoList
                            items={[
                                {
                                    label: t('Ad'),
                                    value: quote.customer.name,
                                },
                                {
                                    label: t('Firma'),
                                    value: quote.customer.company,
                                },
                                {
                                    label: t('E-posta'),
                                    value: quote.customer.email,
                                },
                                {
                                    label: t('Telefon'),
                                    value: quote.customer.phone,
                                },
                                {
                                    label: t('Şehir'),
                                    value: quote.customer.city,
                                },
                                {
                                    label: t('Hesap'),
                                    value: quote.customer.member
                                        ? t('Üye')
                                        : t(
                                              'Misafir (e-postadaki bağlantıyla yanıtlar)',
                                          ),
                                },
                            ]}
                        />
                    </SectionCard>
                    {quote.customer_note ? (
                        <SectionCard title={t('Müşterinin talep notu')}>
                            <p className="text-sm whitespace-pre-line">
                                {quote.customer_note}
                            </p>
                        </SectionCard>
                    ) : null}
                    <HistoryCard histories={quote.histories} />
                    {quote.can_cancel ? <CancelDialog quote={quote} /> : null}
                </>
            ) : (
                <SectionCard title={t('Nasıl işler?')}>
                    <ol className="flex list-decimal flex-col gap-2 pl-4 text-sm text-muted-foreground">
                        <li>{t('Müşteriyi seçip ürünleri ekleyin.')}</li>
                        <li>
                            {t(
                                'Fiyatları girin; "Kaydet" taslak olarak saklar.',
                            )}
                        </li>
                        <li>
                            {t(
                                '"Kaydet ve gönder" ile müşteriye e-posta gider.',
                            )}
                        </li>
                        <li>
                            {t(
                                'Müşteri onaylar ve öder (sipariş oluşur), revize ister ya da reddeder.',
                            )}
                        </li>
                    </ol>
                </SectionCard>
            )}
        </EntitySidebar>
    );
}
