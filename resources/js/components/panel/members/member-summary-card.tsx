import { MemberStatusBadge } from '@/components/panel/member-parts';
import { CardColumn } from '@/components/panel/members/card-column';
import { Row } from '@/components/panel/members/card-row';
import { Stat } from '@/components/panel/members/stat';
import type { MemberDetail } from '@/components/panel/members/types';
import { Card } from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { MemberBilling, MemberOverview } from '@/types';

/** Üst özet kartı: kimlik, fatura, ticari sütunları + istatistik şeridi. */
export function MemberSummaryCard({
    user,
    billing,
    overview,
}: {
    user: MemberDetail;
    billing: MemberBilling | null;
    overview: MemberOverview;
}) {
    const isDealer = user.customer_type === 'dealer';
    const { stats, quotes, returns, addresses } = overview;
    const shippingAddresses = addresses.filter((a) => a.type !== 'billing');
    const defaultShipping =
        shippingAddresses.find((a) => a.is_default_shipping) ??
        shippingAddresses[0];
    const phone =
        (defaultShipping ?? addresses.find((a) => a.phone))?.phone ?? null;

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="grid divide-y lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                <CardColumn title={t('Kimlik ve iletişim')}>
                    <Row label={t('Ad soyad')}>{user.name}</Row>
                    <Row label={t('Firma')}>{user.company}</Row>
                    <Row label={t('E-posta')}>
                        <a
                            href={`mailto:${user.email}`}
                            className="break-all hover:underline"
                        >
                            {user.email}
                        </a>
                    </Row>
                    <Row label={t('Telefon')}>{phone}</Row>
                    <Row label={t('E-posta doğrulama')}>
                        {user.email_verified_at
                            ? formatDate(user.email_verified_at)
                            : t('Doğrulanmadı')}
                    </Row>
                    <Row label={t('Kayıt tarihi')}>
                        {formatDate(user.created_at)}
                    </Row>
                    <Row label={t('Son giriş')}>
                        {formatDateTime(
                            user.last_login_at as string | null | undefined,
                        )}
                    </Row>
                </CardColumn>

                <CardColumn title={t('Fatura ve vergi')}>
                    {billing ? (
                        <>
                            <Row label={t('Fatura türü')}>
                                {billing.type_label}
                            </Row>
                            <Row
                                label={
                                    billing.type === 'corporate'
                                        ? t('Ünvan')
                                        : t('Ad soyad')
                                }
                            >
                                {billing.title}
                            </Row>
                            {billing.type === 'corporate' ? (
                                <>
                                    <Row label={t('Vergi dairesi')}>
                                        {billing.tax_office}
                                    </Row>
                                    <Row label={t('Vergi no')}>
                                        {billing.tax_number}
                                    </Row>
                                </>
                            ) : (
                                <Row label={t('TC kimlik no')}>
                                    {billing.identity_number}
                                </Row>
                            )}
                            <Row label={t('E-fatura e-postası')}>
                                {billing.invoice_email}
                            </Row>
                            <Row label={t('Adres')}>
                                {billing.address
                                    ? [
                                          `${billing.address}${billing.postal_code ? ` ${billing.postal_code}` : ''}`,
                                          [
                                              billing.district,
                                              billing.city,
                                              billing.country,
                                          ]
                                              .filter(Boolean)
                                              .join(' / '),
                                      ]
                                          .filter(Boolean)
                                          .join(', ')
                                    : null}
                            </Row>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('Üye henüz fatura bilgisi girmedi.')}
                        </p>
                    )}
                </CardColumn>

                <CardColumn title={t('Ticari')}>
                    <Row label={t('Hesap türü')}>
                        {isDealer
                            ? t('Bayi')
                            : user.customer_type === 'customer'
                              ? t('Müşteri')
                              : t('Hesap açılmadı')}
                    </Row>
                    {isDealer ? (
                        <>
                            <Row label={t('Bayi grubu')}>
                                {user.dealer_group?.name ?? t('Grupsuz')}
                            </Row>
                            <Row label={t('Uygulanan indirim')}>
                                %{user.effective_discount_rate}
                                {user.discount_rate !== null &&
                                    t(' (bayiye özel)')}
                            </Row>
                            <Row label={t('Bölge deposu')}>
                                {user.warehouse?.name ??
                                    t('Atanmamış (tüm depolar)')}
                            </Row>
                            {user.effective_terms && (
                                <Row label={t('Çalışma şekli')}>
                                    {user.effective_terms.label}
                                    {user.effective_terms.terms === 'credit' &&
                                        t(' · :term_days gün', {
                                            term_days:
                                                user.effective_terms.term_days,
                                        })}
                                </Row>
                            )}
                        </>
                    ) : null}
                    <Row label={t('Durum')}>
                        <MemberStatusBadge status={user.status} />
                    </Row>
                    <Row label={t('Varsayılan teslimat')}>
                        {defaultShipping
                            ? [defaultShipping.district, defaultShipping.city]
                                  .filter(Boolean)
                                  .join(' / ')
                            : null}
                    </Row>
                </CardColumn>
            </div>

            <div className="grid grid-cols-2 gap-px border-t bg-border sm:grid-cols-3 lg:grid-cols-6">
                <Stat label={t('Sipariş')}>{stats.orders_count}</Stat>
                <Stat label={t('Toplam harcama')}>{stats.total_spent}</Stat>
                <Stat label={t('Ortalama sipariş')}>
                    {stats.average_order ?? '—'}
                </Stat>
                <Stat label={t('Son sipariş')}>
                    {stats.last_order_at
                        ? formatDate(stats.last_order_at)
                        : '—'}
                </Stat>
                <Stat label={t('Teklif')}>{quotes.total}</Stat>
                <Stat label={t('İade')}>{returns.total}</Stat>
            </div>
        </Card>
    );
}
