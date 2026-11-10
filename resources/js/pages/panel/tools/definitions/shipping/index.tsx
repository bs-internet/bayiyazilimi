import { Form, Head, Link } from '@inertiajs/react';
import { Save } from 'lucide-react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import { TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index as carriersIndex } from '@/routes/panel/shop/carriers';
import { edit, update } from '@/routes/panel/shop/shipping-settings';

type Props = {
    settings: Record<string, string | null>;
};

export default function ShippingSettings({ settings: s }: Props) {
    return (
        <>
            <Head title={t('Kargo Ayarları')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Kargo Ayarları')}
                    description={
                        <>
                            {t('Kargo ücreti, sepetin toplam desisine göre')}{' '}
                            <Link
                                href={carriersIndex()}
                                className="font-medium text-primary underline"
                            >
                                {t('Kargo Firmaları')}
                            </Link>{' '}
                            {t(
                                "ekranındaki desi tablosundan hesaplanır. Tutarlar TL'dir.",
                            )}
                        </>
                    }
                />

                <Form {...update.form()} options={{ preserveScroll: true }}>
                    {({ processing, errors }) => (
                        <SectionCard title={t('Kargo')}>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <TextField
                                        name="shipping_fee"
                                        label={t('Sabit kargo ücreti')}
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        defaultValue={s.shipping_fee}
                                        error={errors.shipping_fee}
                                        description={t(
                                            'Yalnızca aktif kargo firması yokken kullanılır. 0 = ücretsiz.',
                                        )}
                                    />
                                    <TextField
                                        name="default_product_desi"
                                        label={t('Varsayılan ürün desisi')}
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        defaultValue={s.default_product_desi}
                                        error={errors.default_product_desi}
                                        description={t(
                                            'Desisi girilmemiş ürünler için adet başına kullanılır.',
                                        )}
                                    />
                                    <TextField
                                        name="free_shipping_threshold"
                                        label={t('Ücretsiz kargo eşiği')}
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        defaultValue={s.free_shipping_threshold}
                                        error={errors.free_shipping_threshold}
                                        description={t(
                                            'Sepet bu tutarı geçerse kargo ücretsiz. 0 = eşik yok.',
                                        )}
                                    />
                                </div>
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
                            </FieldGroup>
                        </SectionCard>
                    )}
                </Form>
            </Page>
        </>
    );
}

ShippingSettings.layout = {
    breadcrumbs: [{ title: t('Kargo Ayarları'), href: edit() }],
};
