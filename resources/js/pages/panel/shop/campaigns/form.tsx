import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { ActionSection } from '@/components/panel/campaigns/action-section';
import { AudienceSection } from '@/components/panel/campaigns/audience-section';
import { GeneralSection } from '@/components/panel/campaigns/general-section';
import { ProductSection } from '@/components/panel/campaigns/product-section';
import type {
    FormData,
    Props,
    Rule,
    SectionProps,
} from '@/components/panel/campaigns/types';
import { defaultSettings } from '@/components/panel/campaigns/types';
import { UsageSection } from '@/components/panel/campaigns/usage-section';
import { StatusSwitch } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import {
    destroy,
    index,
    status as statusRoute,
    store,
    update,
} from '@/routes/panel/shop/campaigns';
import type { ProductPick } from '@/types';

export default function CampaignForm({
    campaign,
    conditions,
    actions,
    products: initialProducts,
    currencies,
    baseCurrency,
    otherCampaigns,
    storefront,
}: Props) {
    const audienceDefs = conditions.filter((c) => c.scope === 'audience');
    const productDefs = conditions.filter((c) => c.scope === 'product');
    const [products, setProducts] =
        useState<Record<string, ProductPick>>(initialProducts);
    const rememberProducts = (picked: ProductPick[]) =>
        setProducts((current) => ({
            ...current,
            ...Object.fromEntries(picked.map((p) => [p.id, p])),
        }));

    const form = useForm<FormData>({
        name: campaign?.name ?? '',
        badge: campaign?.badge ?? '',
        starts_at: campaign?.starts_at ?? '',
        ends_at: campaign?.ends_at ?? '',
        sort_order: String(campaign?.sort_order ?? 0),
        audience_rules: campaign?.audience_rules ?? [],
        product_scope: campaign?.product_scope ?? 'all',
        product_rules: campaign?.product_rules ?? [],
        action: campaign?.action ?? actions[0]?.key ?? '',
        settings: campaign
            ? Object.fromEntries(
                  Object.entries(campaign.settings).map(([k, v]) => [
                      k,
                      v === null
                          ? ''
                          : typeof v === 'object'
                            ? JSON.stringify(v)
                            : String(v),
                  ]),
              )
            : defaultSettings(actions[0]),
        shipping_mode: campaign?.shipping_mode ?? 'cart',
        shipping_amount: campaign?.shipping_amount ?? '',
        currency_code: campaign?.currency_code ?? baseCurrency,
        combinable: campaign?.combinable ?? [],
        usage_limit: campaign?.usage_limit?.toString() ?? '',
        usage_limit_per_customer:
            campaign?.usage_limit_per_customer?.toString() ?? '',
        budget: campaign?.budget ?? '',
    });
    const { data, setData, processing } = form;
    const errors = form.errors as Record<string, string>;
    const action = actions.find((a) => a.key === data.action);
    const hasMoney = Boolean(action?.fields.some((f) => f.type === 'money'));
    const symbolOf = (code: string) =>
        currencies.find((c) => c.value === code)?.symbol ?? code;

    const setGroups = (groups: Rule[][]) =>
        setData(
            'audience_rules',
            groups.filter((g) => g.length > 0),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (campaign) {
            form.put(update.url(campaign.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const shared: SectionProps = {
        form,
        data,
        setData,
        errors,
        campaign,
        actions,
        action,
        audienceDefs,
        productDefs,
        products,
        rememberProducts,
        setGroups,
        hasMoney,
        symbolOf,
        currencies,
        baseCurrency,
        storefront,
        otherCampaigns,
    };

    const title = campaign ? campaign.name : t('Yeni kampanya');

    return (
        <>
            <Head title={title} />

            <Page embedded>
                <PageTitle
                    compact
                    title={title}
                    actions={
                        campaign ? (
                            <div className="flex items-center gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    {t('Aktif')}
                                </span>
                                <StatusSwitch
                                    status={campaign.status}
                                    url={statusRoute.url(campaign.id)}
                                    label={t('Kampanya durumunu değiştir')}
                                />
                            </div>
                        ) : undefined
                    }
                    description={
                        campaign
                            ? undefined
                            : t(
                                  'Kampanya pasif oluşturulur; ayarları kontrol edip aktifleştirin.',
                              )
                    }
                />

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <GeneralSection {...shared} />
                    <AudienceSection {...shared} />
                    <ProductSection {...shared} />
                    <ActionSection {...shared} />
                    <UsageSection {...shared} />

                    <FormActions
                        destroy={
                            campaign ? (
                                <DeleteButton
                                    action={destroy(campaign.id)}
                                    title={t(
                                        '":campaign_name" kampanyası silinsin mi?',
                                        { campaign_name: campaign.name },
                                    )}
                                    description={t(
                                        'Verilmiş siparişler etkilenmez. Bu işlem geri alınamaz.',
                                    )}
                                />
                            ) : null
                        }
                    >
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Save data-icon="inline-start" />
                            )}
                            {campaign ? t('Kaydet') : t('Kampanyayı oluştur')}
                        </Button>
                    </FormActions>
                </form>
            </Page>
        </>
    );
}

CampaignForm.layout = {
    breadcrumbs: [{ title: t('Kampanyalar'), href: index() }],
};
