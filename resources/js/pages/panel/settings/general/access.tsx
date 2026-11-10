import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { SwitchField, TextareaField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { update } from '@/routes/panel/shop/site-access';

type Props = {
    settings: Record<string, string | null>;
};

const on = (v: string | null | undefined) =>
    v === '1' || v === 'true' || v === 'on';

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-6 border-b py-7 last:border-b-0 lg:grid-cols-[240px_1fr]">
            <div className="flex flex-col gap-1">
                <h2 className="text-sm font-medium">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <div className="min-w-0">
                <FieldGroup className="gap-5">{children}</FieldGroup>
            </div>
        </section>
    );
}

export default function SiteAccess({ settings: s }: Props) {
    const [maintenance, setMaintenance] = useState(on(s.maintenance_mode));

    return (
        <>
            <Head title={t('Site Erişimi')} />

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="max-w-5xl"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                            <div className="min-w-0">
                                <h1 className="text-xl font-semibold">
                                    {t('Site erişimi')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Site yalnız bayilere açıktır (ziyaretçilere açan bir eklenti kuruluysa herkese). Panel her durumda yöneticilere açıktır.',
                                    )}
                                </p>
                            </div>
                        </div>

                        <Section
                            title={t('Bakım modu')}
                            description={t(
                                'Site kapalıyken ziyaretçiler bakım sayfasını görür; yalnız yöneticiler girebilir.',
                            )}
                        >
                            <SwitchField
                                name="maintenance_mode"
                                label={t('Bakım modu')}
                                description={t(
                                    'Site kapalı, yalnız yöneticiler girebilir. Ziyaretçiler bakım sayfasını görür.',
                                )}
                                defaultChecked={on(s.maintenance_mode)}
                                onChange={setMaintenance}
                            />
                            <div hidden={!maintenance}>
                                <TextareaField
                                    name="maintenance_message"
                                    label={t('Bakım sayfası mesajı')}
                                    rows={3}
                                    defaultValue={s.maintenance_message}
                                    error={errors.maintenance_message}
                                />
                            </div>
                        </Section>

                        <div className="flex justify-end pt-5">
                            <Button disabled={processing}>
                                <Save data-icon="inline-start" />
                                {t('Kaydet')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

SiteAccess.layout = {
    breadcrumbs: [
        {
            title: t('Site Erişimi'),
            href: dashboard(),
        },
    ],
};
