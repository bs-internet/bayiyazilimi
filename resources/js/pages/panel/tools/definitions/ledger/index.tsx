import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import { SwitchField, TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { edit, update } from '@/routes/panel/shop/ledger-settings';

type Props = {
    currency: string;
    methods: { key: string; label: string; min_amount: string | null }[];
    dueWarningDays: number;
    chequesEnabled: boolean;
    chequeWarningDays: number;
};

export default function LedgerSettings({
    currency,
    methods,
    dueWarningDays,
    chequesEnabled,
    chequeWarningDays,
}: Props) {
    const [cheques, setCheques] = useState(chequesEnabled);

    return (
        <>
            <Head title={t('Cari Ayarları')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Cari Ayarları')}
                    description={t(
                        'Ödeme yöntemlerinin en düşük sipariş tutarı, vade uyarısı ve çek / senet portföyü. Peşin / vadeli çalışma, limit ve vade bayi gruplarında (bayi bazında değiştirilebilir).',
                    )}
                />

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <SectionCard
                                title={t('Ödeme yöntemleri')}
                                description={t(
                                    'Yöntemin sunulduğu en düşük sipariş tutarı (:currency, KDV dahil). Boş: sınır yok.',
                                    { currency },
                                )}
                            >
                                <FieldGroup>
                                    {methods.map((m) => (
                                        <TextField
                                            key={m.key}
                                            name={`min_amounts[${m.key}]`}
                                            label={m.label}
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            defaultValue={m.min_amount ?? ''}
                                            placeholder={t('Sınır yok')}
                                            error={
                                                errors[`min_amounts.${m.key}`]
                                            }
                                        />
                                    ))}
                                </FieldGroup>
                            </SectionCard>

                            <SectionCard title={t('Vade')}>
                                <FieldGroup>
                                    <TextField
                                        name="due_warning_days"
                                        label={t('Vade uyarı süresi (gün)')}
                                        type="number"
                                        min={0}
                                        required
                                        defaultValue={dueWarningDays}
                                        error={errors.due_warning_days}
                                        description={t(
                                            "Vadesine bu kadar gün kalan borçlar bayinin Cari Hesabım ekranında ve müşteri detayında 'vadesi yaklaşan' olarak gösterilir.",
                                        )}
                                    />
                                </FieldGroup>
                            </SectionCard>

                            <SectionCard title={t('Çek / senet')}>
                                <FieldGroup>
                                    <SwitchField
                                        name="cheques_enabled"
                                        label={t(
                                            'Çek / senet portföyünü kullan',
                                        )}
                                        description={t(
                                            'Açıkken tahsilatta çek ve senet seçilebilir, Cari menüsünde Çek / Senet portföyü görünür. Kapalıyken hiçbir ekranda yer almaz; kayıtlı çek / senetler silinmez.',
                                        )}
                                        defaultChecked={chequesEnabled}
                                        onChange={setCheques}
                                    />
                                    {cheques && (
                                        <TextField
                                            name="cheque_warning_days"
                                            label={t(
                                                'Vadesi yaklaşan uyarısı (gün)',
                                            )}
                                            type="number"
                                            min={0}
                                            defaultValue={chequeWarningDays}
                                            error={errors.cheque_warning_days}
                                            description={t(
                                                'Vadesine bu kadar gün kalan portföydeki çek / senetler listede işaretlenir.',
                                            )}
                                        />
                                    )}
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
                        </>
                    )}
                </Form>
            </Page>
        </>
    );
}

LedgerSettings.layout = {
    breadcrumbs: [{ title: t('Cari Ayarları'), href: edit() }],
};
