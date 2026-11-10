import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import { SelectField, TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { edit, update } from '@/routes/panel/shop/sales-settings';
import type { ShopOption } from '@/types';

type Props = {
    settings: Record<string, string | null>;
    /** Vitrin kurulu mu? Yoksa ziyaretçi / müşteri fiyat gösterimi gizli. */
    storefront: boolean;
};

const PRICE_DISPLAY_OPTIONS: ShopOption[] = [
    { value: 'gross', label: t('KDV dahil') },
    { value: 'net', label: t('KDV hariç (girildiği gibi)') },
];

export default function SalesSettings({ settings: s, storefront }: Props) {
    return (
        <>
            <Head title={t('Satış Ayarları')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Satış Ayarları')}
                    description={t(
                        'Fiyatların KDV dahil / hariç gösterimi, teklif geçerlilik ve iade talebi süreleri.',
                    )}
                />

                <Form {...update.form()} options={{ preserveScroll: true }}>
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-6">
                            <SectionCard title={t('Fiyat gösterimi')}>
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {storefront ? (
                                            <SelectField
                                                name="price_display"
                                                label={t('Ziyaretçi / müşteri')}
                                                options={PRICE_DISPLAY_OPTIONS}
                                                required
                                                defaultValue={s.price_display}
                                                error={errors.price_display}
                                            />
                                        ) : null}
                                        <SelectField
                                            name="dealer_price_display"
                                            label={t('Bayi')}
                                            options={PRICE_DISPLAY_OPTIONS}
                                            required
                                            defaultValue={
                                                s.dealer_price_display
                                            }
                                            error={errors.dealer_price_display}
                                        />
                                    </div>
                                </FieldGroup>
                            </SectionCard>

                            <SectionCard title={t('Teklif ve iade')}>
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <TextField
                                            name="quote_valid_days"
                                            label={t(
                                                'Teklif geçerlilik süresi (gün)',
                                            )}
                                            type="number"
                                            min={1}
                                            required
                                            defaultValue={s.quote_valid_days}
                                            error={errors.quote_valid_days}
                                            description={t(
                                                'Onaylanan teklif bu süre içinde siparişe dönüştürülebilir.',
                                            )}
                                        />
                                        <TextField
                                            name="return_days"
                                            label={t(
                                                'İade talebi süresi (gün)',
                                            )}
                                            type="number"
                                            min={0}
                                            required
                                            defaultValue={s.return_days}
                                            error={errors.return_days}
                                            description={t(
                                                'Sipariş kargoya verildikten sonra bu süre içinde iade talebi açılabilir. 0 = iade kapalı.',
                                            )}
                                        />
                                    </div>
                                </FieldGroup>
                            </SectionCard>

                            <SectionCard title={t('Bildirimler')}>
                                <FieldGroup>
                                    <TextField
                                        name="order_notification_email"
                                        label={t('Bildirim e-postası')}
                                        type="email"
                                        defaultValue={
                                            s.order_notification_email
                                        }
                                        error={errors.order_notification_email}
                                        description={t(
                                            'Yeni sipariş, ödeme, teklif ve iade talepleri bu adrese bildirilir. Boşsa yöneticilere e-posta gitmez.',
                                        )}
                                    />
                                </FieldGroup>
                            </SectionCard>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {t('Kaydet')}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </Page>
        </>
    );
}

SalesSettings.layout = {
    breadcrumbs: [{ title: t('Satış Ayarları'), href: edit() }],
};
