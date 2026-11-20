import { Form, Head } from '@inertiajs/react';
import { CircleCheck, CircleAlert, Save } from 'lucide-react';
import type { ReactNode } from 'react';

import { TextField } from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { update } from '@/routes/panel/shop/backup';

type Props = {
    mailTo: string;
    passwordSet: boolean;
    envMailTo: string;
    envPasswordSet: boolean;
    /** Adres ve şifre (ayar ya da .env) tanımlı: yedek e-postayla gidiyor. */
    active: boolean;
    at: string;
    keep: number;
    /** Yalnız Super Admin değiştirir. */
    canEdit: boolean;
};

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

export default function BackupSettings({
    mailTo,
    passwordSet,
    envMailTo,
    envPasswordSet,
    active,
    at,
    keep,
    canEdit,
}: Props) {
    return (
        <>
            <Head title={t('Yedekleme')} />

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess={['backup_mail_password']}
                className="max-w-5xl"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                            <div className="min-w-0">
                                <h1 className="text-xl font-semibold">
                                    {t('Yedekleme')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Veritabanı her gün :at saatinde yedeklenir; sunucuda son :keep yedek saklanır.',
                                        { at, keep: String(keep) },
                                    )}
                                </p>
                            </div>
                            <p
                                className={`flex items-center gap-1.5 text-sm font-medium ${active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'}`}
                            >
                                {active ? (
                                    <CircleCheck className="size-4" />
                                ) : (
                                    <CircleAlert className="size-4" />
                                )}
                                {active
                                    ? t('Yedek e-postayla gönderiliyor')
                                    : t('Yedek e-postayla gönderilmiyor')}
                            </p>
                        </div>

                        <Section
                            title={t('E-postayla gönderim')}
                            description={t(
                                'Günlük yedek bu adreslere şifreli zip eki olarak gönderilir. Şifre olmadan yedek hiç gönderilmez; şifreyi güvenli bir yerde saklayın, zip yalnız onunla açılır.',
                            )}
                        >
                            <fieldset
                                disabled={!canEdit}
                                className="flex flex-col gap-5"
                            >
                                <TextField
                                    name="backup_mail_to"
                                    label={t('E-posta adresleri')}
                                    defaultValue={mailTo}
                                    placeholder={envMailTo || 'yedek@ornek.com'}
                                    error={errors.backup_mail_to}
                                    description={
                                        envMailTo && !mailTo
                                            ? t(
                                                  'Birden çok adres virgülle ayrılır. Boşken sunucu ayarındaki adres kullanılır: :address',
                                                  { address: envMailTo },
                                              )
                                            : t(
                                                  'Birden çok adres virgülle ayrılır. Boş bırakılırsa yedek e-postayla gönderilmez.',
                                              )
                                    }
                                />
                                <TextField
                                    name="backup_mail_password"
                                    type="password"
                                    label={t('Zip şifresi')}
                                    error={errors.backup_mail_password}
                                    placeholder={
                                        passwordSet ? '••••••••' : undefined
                                    }
                                    description={
                                        passwordSet
                                            ? t(
                                                  'Şifre kayıtlı; değiştirmek için yenisini yazın, boş bırakılırsa korunur.',
                                              )
                                            : envPasswordSet
                                              ? t(
                                                    'Boşken sunucu ayarındaki şifre kullanılır. En az 8 karakter.',
                                                )
                                              : t('En az 8 karakter.')
                                    }
                                />
                            </fieldset>
                            {!canEdit ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Yedek tüm veritabanını içerir; bu ayarları yalnız Super Admin değiştirebilir.',
                                    )}
                                </p>
                            ) : null}
                        </Section>

                        {canEdit ? (
                            <div className="flex justify-end pt-5">
                                <Button disabled={processing}>
                                    <Save data-icon="inline-start" />
                                    {t('Kaydet')}
                                </Button>
                            </div>
                        ) : null}
                    </div>
                )}
            </Form>
        </>
    );
}

BackupSettings.layout = {
    breadcrumbs: [
        {
            title: t('Yedekleme'),
            href: dashboard(),
        },
    ],
};
