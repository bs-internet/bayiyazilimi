import { Form, Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    FileDown,
    FileSpreadsheet,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import InputError from '@/components/input-error';
import { CheckField } from '@/components/shop/check-field';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { products as exportProducts } from '@/routes/panel/shop/export';
import { index, store, template } from '@/routes/panel/shop/import';
import { index as productsIndex } from '@/routes/panel/shop/products';

type ImportResult = {
    created: number;
    updated: number;
    skipped: number;
    errors: string[];
};

type Props = {
    result: ImportResult | null;
    productCount: number;
};

function Stat({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className?: string;
}) {
    return (
        <div className="rounded-lg border p-4">
            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tabular-nums',
                    className,
                )}
            >
                {value}
            </div>
        </div>
    );
}

export default function ImportIndex({ result, productCount }: Props) {
    const [fileName, setFileName] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);

    return (
        <>
            <Head title={t('İçe / dışa aktar')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Katalog') },
                        { title: t('Ürünler'), href: productsIndex() },
                        { title: t('İçe aktar') },
                    ]}
                    title={t('İçe / dışa aktar')}
                    meta={
                        <span className="tabular-nums">
                            {t('Sistemde :product_count ürün var', {
                                product_count: productCount,
                            })}
                        </span>
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportProducts.url()}>
                                <FileDown data-icon="inline-start" />
                                {t('Tüm ürünleri dışa aktar')}
                            </a>
                        </Button>
                    }
                />

                <EntityLayout>
                    <EntityMain>
                        <Form
                            {...store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            onSuccess={() => setFileName(null)}
                        >
                            {({ processing, errors, progress }) => (
                                <SectionCard
                                    title={t('Dosya yükle')}
                                    description={t(
                                        'Ürünler Stok Kodu ile eşleştirilir; dosyada olmayan sütunlara dokunulmaz.',
                                    )}
                                    footer={
                                        <div className="flex w-full justify-end">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : (
                                                    <Upload data-icon="inline-start" />
                                                )}
                                                {processing
                                                    ? t('Aktarılıyor…')
                                                    : t('İçe aktar')}
                                            </Button>
                                        </div>
                                    }
                                >
                                    <FieldGroup>
                                        <label
                                            htmlFor="file"
                                            onDragOver={() => setDragOver(true)}
                                            onDragLeave={() =>
                                                setDragOver(false)
                                            }
                                            onDrop={() => setDragOver(false)}
                                            className={cn(
                                                'relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-10 text-center transition hover:border-primary hover:bg-primary/5',
                                                dragOver &&
                                                    'border-primary bg-primary/5',
                                                errors.file &&
                                                    'border-destructive',
                                            )}
                                        >
                                            <FileSpreadsheet className="size-8 text-primary" />
                                            {fileName ? (
                                                <span className="font-medium">
                                                    {fileName}
                                                </span>
                                            ) : (
                                                <>
                                                    <span className="font-medium">
                                                        {t(
                                                            'Excel dosyasını seçin ya da buraya bırakın',
                                                        )}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground">
                                                        {t(
                                                            '.xlsx · en fazla 50 MB',
                                                        )}
                                                    </span>
                                                </>
                                            )}
                                            <input
                                                id="file"
                                                name="file"
                                                type="file"
                                                accept=".xlsx"
                                                required
                                                onChange={(e) =>
                                                    setFileName(
                                                        e.target.files?.[0]
                                                            ?.name ?? null,
                                                    )
                                                }
                                                className="absolute inset-0 cursor-pointer opacity-0"
                                            />
                                        </label>
                                        <InputError message={errors.file} />
                                        {progress && (
                                            <Progress
                                                value={progress.percentage}
                                            />
                                        )}
                                        <CheckField
                                            name="update_existing"
                                            label={t(
                                                'Mevcut ürünleri güncelle',
                                            )}
                                            description={t(
                                                "Kapalıysa yalnızca yeni SKU'lar eklenir.",
                                            )}
                                            defaultChecked
                                        />
                                        <CheckField
                                            name="update_stock"
                                            label={t(
                                                'Depo stoklarını dosyadaki değerlere eşitle',
                                            )}
                                            description={t(
                                                'Farklar stok hareketi (İçe Aktarım) olarak kaydedilir.',
                                            )}
                                            defaultChecked
                                        />
                                        <CheckField
                                            name="download_images"
                                            label={t(
                                                'Görsel bağlantılarını indir',
                                            )}
                                            description={t(
                                                'Görseli olmayan ürünlere Görsel 1–5 sütunlarındaki adreslerden görsel eklenir. Çok sayıda görselde aktarım uzar.',
                                            )}
                                        />
                                    </FieldGroup>
                                </SectionCard>
                            )}
                        </Form>

                        {result && (
                            <SectionCard
                                title={t('Son içe aktarma sonucu')}
                                action={
                                    result.errors.length > 0 ? (
                                        <span className="flex items-center gap-1.5 text-sm text-destructive">
                                            <AlertTriangle className="size-4" />
                                            {t(':count uyarı', {
                                                count: result.errors.length,
                                            })}
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400">
                                            <CheckCircle2 className="size-4" />
                                            {t('Sorunsuz')}
                                        </span>
                                    )
                                }
                            >
                                <div className="flex flex-col gap-4">
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Stat
                                            label={t('Yeni')}
                                            value={result.created}
                                            className="text-emerald-700 dark:text-emerald-400"
                                        />
                                        <Stat
                                            label={t('Güncellenen')}
                                            value={result.updated}
                                            className="text-primary"
                                        />
                                        <Stat
                                            label={t('Atlanan')}
                                            value={result.skipped}
                                            className="text-muted-foreground"
                                        />
                                    </div>
                                    {result.errors.length > 0 && (
                                        <ul className="max-h-72 divide-y overflow-y-auto rounded-lg border text-sm">
                                            {result.errors.map((e, i) => (
                                                <li
                                                    key={i}
                                                    className="flex gap-2 px-3 py-2"
                                                >
                                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-destructive" />
                                                    <span>{e}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </SectionCard>
                        )}
                    </EntityMain>

                    <EntitySidebar>
                        <SectionCard
                            title={t('Ürün şablonu')}
                            footer={
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <a href={template.url()}>
                                        <FileDown data-icon="inline-start" />
                                        {t('Boş şablonu indir')}
                                    </a>
                                </Button>
                            }
                        >
                            <ol className="flex list-decimal flex-col gap-3 pl-4 text-sm text-muted-foreground marker:font-semibold marker:text-foreground">
                                <li>
                                    {t('Yeni ürünler için')}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('boş şablonu')}
                                    </span>
                                    {t(', mevcut ürünleri düzenlemek için')}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('dışa aktarılan dosyayı')}
                                    </span>{' '}
                                    {t(
                                        'kullanın; ikisi de aynı sütunlarla iner.',
                                    )}
                                </li>
                                <li>
                                    {t(
                                        'Sütunların ne anlama geldiği ve örnek değerler dosyanın "Açıklamalar" sayfasındadır.',
                                    )}
                                </li>
                                <li>
                                    {t('Ürünler')}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('Stok Kodu')}
                                    </span>{' '}
                                    {t(
                                        'ile eşleşir; yeni kodlar yeni ürün olarak eklenir.',
                                    )}
                                </li>
                            </ol>
                        </SectionCard>

                        <SectionCard title={t('Kısmi güncelleme')}>
                            <div className="flex flex-col gap-3 text-sm text-muted-foreground">
                                <p>
                                    {t(
                                        'Dosyada olmayan sütunlara dokunulmaz. Örneğin yalnız',
                                    )}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('Stok Kodu')}
                                    </span>{' '}
                                    {t('ve')}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('Fiyat')}
                                    </span>{' '}
                                    {t('sütunlarıyla toplu fiyat,')}{' '}
                                    <span className="font-medium text-foreground">
                                        {t('Stok: DEPO')}
                                    </span>{' '}
                                    {t('sütunlarıyla stok güncellenir.')}
                                </p>
                                <p>
                                    {t(
                                        'Boş hücre metin ve Evet/Hayır alanlarını temizler; ad, fiyat, para birimi, KDV, satış biçimi, durum ve birim boşsa değişmez.',
                                    )}
                                </p>
                            </div>
                        </SectionCard>
                    </EntitySidebar>
                </EntityLayout>
            </Page>
        </>
    );
}

ImportIndex.layout = {
    breadcrumbs: [{ title: t('İçe aktar'), href: index() }],
};
