import { Form } from '@inertiajs/react';
import { AlertCircle, ExternalLink, ImagePlus, Save } from 'lucide-react';
import { useState } from 'react';

import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { CheckField } from '@/components/shop/check-field';
import { RichTextField } from '@/components/shop/form-fields';
import { ProductImages } from '@/components/shop/product-images';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Slot } from '@/lib/extensions';
import { t } from '@/lib/i18n';
import {
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/panel/shop/products';
import type { ShopOption } from '@/types';

import { SelectInput, TextAreaInput, TextInput } from './fields';
import { PricingSection } from './pricing-section';
import { StockSection } from './stock-section';
import type { ProductFormData, ProductFormProps } from './types';

export { SelectInput, TextAreaInput, TextInput } from './fields';
export type * from './types';

const STATUS_OPTIONS: ShopOption[] = [
    { value: 'active', label: t('Aktif — satışta') },
    { value: 'passive', label: t('Pasif — gizli') },
];

/** Kaydedilmiş alanlar değişince formu yeniden kur (defaultValue'lar tazelensin). */
function formKey(product: ProductFormData | null): string {
    if (!product) {
        return 'new';
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { images, stocks, ...fields } = product;

    return JSON.stringify(fields);
}

/* ------------------------------------------------------------------ */

export function ProductForm(props: ProductFormProps) {
    return <ProductFormInner key={formKey(props.product)} {...props} />;
}

function ProductFormInner({
    product,
    defaults,
    categories,
    brands,
    saleModes,
    productUrlPrefix,
    extensions,
    currencies,
    taxRates,
    units,
}: ProductFormProps) {
    const p = product;
    const formProps = p ? update.form(p.id) : store.form();

    const [name, setName] = useState(p?.name ?? '');
    const [slug, setSlug] = useState(p?.slug ?? '');
    const [shortDescription, setShortDescription] = useState(
        p?.short_description ?? '',
    );

    // "Sitede gör": yalnız aktif ve herkese açık adresi olan üründe.
    const storefrontUrl = p?.status === 'active' ? p.storefront_url : null;

    return (
        <Form
            {...formProps}
            options={{ preserveScroll: true }}
            disableWhileProcessing
        >
            {({ processing, errors }) => {
                const errorCount = Object.keys(errors).length;

                return (
                    <Page>
                        <PageTitle
                            trail={[
                                { title: t('Katalog') },
                                { title: t('Ürünler'), href: index() },
                                ...(p
                                    ? [{ title: p.name, href: edit(p.id) }]
                                    : [{ title: t('Yeni ürün') }]),
                            ]}
                            title={p ? p.name : t('Yeni ürün')}
                            badges={
                                p ? (
                                    <>
                                        {p.status === 'active' ? (
                                            <Badge className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                                {t('Aktif')}
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                {t('Pasif')}
                                            </Badge>
                                        )}
                                        {p.badges.map((b) => (
                                            <Badge
                                                key={b.label}
                                                className="bg-primary/10 text-primary"
                                            >
                                                {b.label}
                                            </Badge>
                                        ))}
                                    </>
                                ) : undefined
                            }
                            meta={
                                p ? (
                                    <>
                                        <span>
                                            {t('SKU: :sku', { sku: p.sku })}
                                        </span>
                                        <MetaDot />
                                        <span className="tabular-nums">
                                            {p.track_stock
                                                ? t(':stock :unit stok', {
                                                      stock: p.stock,
                                                      unit: p.unit,
                                                  })
                                                : t('Stok takipsiz')}
                                        </span>
                                        <MetaDot />
                                        <span>
                                            {t(':images_length görsel', {
                                                images_length: p.images.length,
                                            })}
                                        </span>
                                    </>
                                ) : (
                                    <span>
                                        {t(
                                            'Ürünü kaydettikten sonra görsel ekleyebilirsiniz.',
                                        )}
                                    </span>
                                )
                            }
                            actions={
                                <>
                                    {storefrontUrl && (
                                        <Button variant="outline" asChild>
                                            <a
                                                href={storefrontUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink data-icon="inline-start" />
                                                {t('Sitede gör')}
                                            </a>
                                        </Button>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {t('Kaydet')}
                                    </Button>
                                </>
                            }
                        />

                        {errorCount > 0 && (
                            <Alert variant="destructive">
                                <AlertCircle />
                                <AlertTitle>
                                    {t('Ürün kaydedilemedi')}
                                </AlertTitle>
                                <AlertDescription>
                                    {t(
                                        ':error_count alanda hata var; işaretli alanları düzeltip yeniden kaydedin.',
                                        { error_count: errorCount },
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}

                        <EntityLayout>
                            <EntityMain>
                                {/* Temel bilgiler */}
                                <SectionCard title={t('Temel bilgiler')}>
                                    <FieldGroup>
                                        <TextInput
                                            name="name"
                                            label={t('Ürün adı')}
                                            required
                                            defaultValue={p?.name}
                                            error={errors.name}
                                            onValueChange={setName}
                                        />
                                        <TextInput
                                            name="slug"
                                            label={t('URL (slug)')}
                                            prefix={
                                                productUrlPrefix ?? undefined
                                            }
                                            defaultValue={p?.slug}
                                            error={errors.slug}
                                            placeholder="urun-adi"
                                            description={t(
                                                'Boş bırakılırsa ürün adından üretilir.',
                                            )}
                                            onValueChange={setSlug}
                                        />
                                        <TextAreaInput
                                            name="short_description"
                                            label={t('Kısa açıklama')}
                                            rows={2}
                                            defaultValue={p?.short_description}
                                            error={errors.short_description}
                                            onValueChange={setShortDescription}
                                        />
                                        <RichTextField
                                            name="description"
                                            label={t('Açıklama')}
                                            minHeight="16rem"
                                            defaultValue={p?.description}
                                            error={errors.description}
                                        />
                                        <TextAreaInput
                                            name="highlights"
                                            label={t('Öne çıkan özellikler')}
                                            rows={4}
                                            defaultValue={p?.highlights}
                                            error={errors.highlights}
                                            description={t(
                                                'Her satır bir madde olarak ürün sayfasında listelenir.',
                                            )}
                                        />
                                        <TextInput
                                            name="notice"
                                            label={t('Uyarı notu')}
                                            defaultValue={p?.notice}
                                            error={errors.notice}
                                            description={t(
                                                "Ürün sayfasında vurgulu gösterilir (ör. 'Sipariş öncesi keşif zorunludur').",
                                            )}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                <PricingSection
                                    product={p}
                                    defaults={defaults}
                                    currencies={currencies}
                                    taxRates={taxRates}
                                    errors={errors}
                                />

                                <StockSection
                                    product={p}
                                    defaults={defaults}
                                    units={units}
                                    errors={errors}
                                />

                                {/* Görseller */}
                                {p ? (
                                    <ProductImages
                                        productId={p.id}
                                        images={p.images}
                                    />
                                ) : (
                                    <SectionCard title={t('Görseller')}>
                                        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed py-10 text-center text-sm text-muted-foreground">
                                            <ImagePlus className="size-6" />
                                            {t(
                                                'Görselleri ürünü kaydettikten sonra ekleyebilirsiniz.',
                                            )}
                                        </div>
                                    </SectionCard>
                                )}

                                {/* Kargo */}
                                <SectionCard title={t('Kargo')}>
                                    <FieldGroup>
                                        <TextInput
                                            name="desi"
                                            label={t('Desi')}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            defaultValue={p?.desi ?? '0'}
                                            error={errors.desi}
                                            description={t(
                                                'Kargo ücreti firmanın desi tarifesine göre hesaplanır.',
                                            )}
                                            className="sm:max-w-60"
                                        />
                                        <CheckField
                                            name="free_shipping"
                                            label={t('Ücretsiz kargo')}
                                            description={t(
                                                'Bu ürün için kargo ücreti alınmaz.',
                                            )}
                                            defaultChecked={
                                                p?.free_shipping ?? false
                                            }
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                {/* Modüllerin bölümleri (ör. vitrin: arama motoru / SEO) */}
                                <Slot
                                    name="product-form.main"
                                    product={p}
                                    errors={errors}
                                    extensions={extensions}
                                    preview={{
                                        name,
                                        slug,
                                        shortDescription,
                                        urlPrefix: productUrlPrefix,
                                    }}
                                />

                                {/* Arama (panel / bayi ürün araması) */}
                                <SectionCard
                                    title={t('Arama')}
                                    description={t(
                                        'Ürün aramasında (bayi paneli, hızlı sipariş) eşleşmesi istenen ek kelimeler.',
                                    )}
                                >
                                    <FieldGroup>
                                        <TextAreaInput
                                            name="search_keywords"
                                            label={t('Arama kelimeleri')}
                                            rows={2}
                                            defaultValue={p?.search_keywords}
                                            error={errors.search_keywords}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                {p && (
                                    <FormActions
                                        destroy={
                                            <DeleteButton
                                                action={destroy(p.id)}
                                                title={t(
                                                    ':p_name silinsin mi?',
                                                    { p_name: p.name },
                                                )}
                                                description={t(
                                                    'Ürün satıştan kaldırılır. Geçmiş sipariş ve teklif kayıtları korunur.',
                                                )}
                                            />
                                        }
                                    >
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <Save data-icon="inline-start" />
                                            )}
                                            {t('Kaydet')}
                                        </Button>
                                    </FormActions>
                                )}
                            </EntityMain>

                            <EntitySidebar>
                                <SectionCard title={t('Yayın')}>
                                    <FieldGroup>
                                        <SelectInput
                                            name="status"
                                            label={t('Durum')}
                                            options={STATUS_OPTIONS}
                                            required
                                            defaultValue={p?.status ?? 'active'}
                                            error={errors.status}
                                        />
                                        <Slot
                                            name="product-form.publish"
                                            product={p}
                                            errors={errors}
                                            extensions={extensions}
                                        />
                                        <SelectInput
                                            name="sale_mode"
                                            label={t('Satış biçimi')}
                                            options={saleModes}
                                            required
                                            defaultValue={
                                                p?.sale_mode ?? 'cart'
                                            }
                                            error={errors.sale_mode}
                                            description={t(
                                                "Yalnızca teklif: fiyat gösterilmez, sepete eklenemez; 'Teklif Al' ile istenir.",
                                            )}
                                        />
                                        <TextInput
                                            name="sort_order"
                                            label={t('Sıra')}
                                            type="number"
                                            min={0}
                                            defaultValue={p?.sort_order ?? 0}
                                            error={errors.sort_order}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                <SectionCard title={t('Organizasyon')}>
                                    <FieldGroup>
                                        <SelectInput
                                            name="category_id"
                                            label={t('Kategori')}
                                            options={categories}
                                            placeholder={t(
                                                '— Kategori seçin —',
                                            )}
                                            defaultValue={p?.category_id}
                                            error={errors.category_id}
                                        />
                                        <SelectInput
                                            name="brand_id"
                                            label={t('Marka')}
                                            options={brands}
                                            placeholder={t('— Marka yok —')}
                                            defaultValue={p?.brand_id}
                                            error={errors.brand_id}
                                        />
                                        <TextInput
                                            name="sku"
                                            label={t('Stok kodu (SKU)')}
                                            required
                                            defaultValue={p?.sku}
                                            error={errors.sku}
                                        />
                                        <TextInput
                                            name="model_number"
                                            label={t('Model no')}
                                            defaultValue={p?.model_number}
                                            error={errors.model_number}
                                        />
                                        <TextInput
                                            name="barcode"
                                            label={t('Barkod')}
                                            defaultValue={p?.barcode}
                                            error={errors.barcode}
                                        />
                                    </FieldGroup>
                                </SectionCard>
                            </EntitySidebar>
                        </EntityLayout>
                    </Page>
                );
            }}
        </Form>
    );
}
