import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { ActiveBadge } from '@/components/admin/active-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { CheckField } from '@/components/shop/check-field';
import { RichTextField, TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { destroy, index, store, update } from '@/routes/panel/shop/contracts';
import type { Contract } from '@/types';

type Props = {
    contract: Contract | null;
    /** Üyelik formu var mı (site herkese açık, vitrin)? Yoksa kutu gösterilmez. */
    registration: boolean;
};

/**
 * Sözleşme ekleme / düzenleme. Durum burada değil, listedeki anahtardan
 * değişir; yeni sözleşme pasif başlar.
 */
export default function ContractEdit({ contract, registration }: Props) {
    const title = contract ? contract.title : t('Yeni sözleşme');
    const formProps = contract ? update.form(contract.id) : store.form();

    return (
        <>
            <Head title={title} />

            <Form
                key={contract?.updated_at ?? 'new'}
                {...formProps}
                options={{ preserveScroll: true }}
            >
                {({ processing, errors }) => (
                    <Page embedded>
                        <PageTitle
                            compact
                            title={title}
                            badges={
                                contract ? (
                                    <ActiveBadge
                                        active={contract.status === 'active'}
                                    />
                                ) : undefined
                            }
                            meta={
                                contract?.updated_at
                                    ? t('Son güncelleme :updated_at', {
                                          updated_at: formatDateTime(
                                              contract.updated_at,
                                          ),
                                      })
                                    : t(
                                          'Kaydettikten sonra listeden aktifleştirin.',
                                      )
                            }
                            actions={
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={index()}>
                                            <ArrowLeft data-icon="inline-start" />
                                            {t('Sözleşmeler')}
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save data-icon="inline-start" />
                                        {t('Kaydet')}
                                    </Button>
                                </>
                            }
                        />

                        <EntityLayout>
                            <EntityMain>
                                <SectionCard title={t('Temel bilgiler')}>
                                    <FieldGroup>
                                        <TextField
                                            name="title"
                                            label={t('Başlık')}
                                            required
                                            defaultValue={contract?.title}
                                            error={errors.title}
                                        />
                                        <TextField
                                            name="slug"
                                            label={t('Kısa ad')}
                                            defaultValue={contract?.slug}
                                            error={errors.slug}
                                            description={t(
                                                'Bayi panelindeki adreste kullanılır. Boş bırakılırsa başlıktan üretilir.',
                                            )}
                                        />
                                    </FieldGroup>
                                </SectionCard>

                                <SectionCard title={t('Metin')}>
                                    <RichTextField
                                        name="content"
                                        label={t('Sözleşme metni')}
                                        minHeight="24rem"
                                        defaultValue={contract?.content}
                                        error={errors.content}
                                    />
                                </SectionCard>

                                {contract ? (
                                    <FormActions
                                        destroy={
                                            <DeleteButton
                                                action={destroy(contract.id)}
                                                title={t(
                                                    ':title silinsin mi?',
                                                    {
                                                        title: contract.title,
                                                    },
                                                )}
                                            />
                                        }
                                    >
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Save data-icon="inline-start" />
                                            {t('Kaydet')}
                                        </Button>
                                    </FormActions>
                                ) : null}
                            </EntityMain>

                            <EntitySidebar>
                                <SectionCard
                                    title={t('Gösterildiği yer')}
                                    description={t(
                                        "İşaretliyse formda onaylatılır. İşaretsizse genel sözleşmedir; aktifse yalnız bayi panelindeki Sözleşmeler'de okunur.",
                                    )}
                                >
                                    <FieldGroup>
                                        <CheckField
                                            name="show_on_checkout"
                                            label={t('Ödeme formunda göster')}
                                            defaultChecked={
                                                contract?.show_on_checkout ??
                                                false
                                            }
                                        />
                                        {registration ? (
                                            <CheckField
                                                name="show_on_registration"
                                                label={t(
                                                    'Üyelik formunda göster',
                                                )}
                                                defaultChecked={
                                                    contract?.show_on_registration ??
                                                    false
                                                }
                                            />
                                        ) : null}
                                        <TextField
                                            name="sort_order"
                                            label={t('Sıra')}
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                contract?.sort_order ?? 0
                                            }
                                            error={errors.sort_order}
                                        />
                                    </FieldGroup>
                                </SectionCard>
                            </EntitySidebar>
                        </EntityLayout>
                    </Page>
                )}
            </Form>
        </>
    );
}

ContractEdit.layout = {
    breadcrumbs: [{ title: t('Sözleşmeler'), href: index() }],
};
