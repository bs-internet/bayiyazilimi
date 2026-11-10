import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Database,
    RefreshCw,
    Rocket,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import InputError from '@/components/input-error';
import { InstallShell } from '@/components/install-shell';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { database as databaseRoute, store } from '@/routes/install';

type Requirement = {
    key: string;
    label: string;
    ok: boolean;
    detail: string | null;
};

type Props = {
    requirements: Requirement[];
    passes: boolean;
    database_ready: boolean;
    database: {
        host: string;
        port: string;
        database: string;
        username: string;
    };
    app_url: string;
};

function RequirementsStep({ requirements }: { requirements: Requirement[] }) {
    const [checking, setChecking] = useState(false);
    const failing = requirements.filter((item) => !item.ok);

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-base font-medium">
                    {t('Sunucu gereksinimleri')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Aşağıdaki eksikler giderilmeden kurulum yapılamaz. Hosting firmanızdan PHP eklentilerini açmasını ve klasör izinlerini düzeltmesini isteyebilirsiniz.',
                    )}
                </p>
            </div>

            <ul className="flex flex-col gap-2 text-sm">
                {[...failing, ...requirements.filter((item) => item.ok)].map(
                    (item) => (
                        <li key={item.key} className="flex items-center gap-2">
                            {item.ok ? (
                                <CircleCheck className="size-4 shrink-0 text-emerald-600" />
                            ) : (
                                <CircleX className="size-4 shrink-0 text-destructive" />
                            )}
                            <span
                                className={cn(
                                    !item.ok && 'font-medium text-destructive',
                                )}
                            >
                                {item.label}
                            </span>
                            {item.detail && (
                                <span className="text-muted-foreground">
                                    ({item.detail})
                                </span>
                            )}
                        </li>
                    ),
                )}
            </ul>

            <Button
                type="button"
                variant="outline"
                className="self-start"
                disabled={checking}
                onClick={() => {
                    setChecking(true);
                    router.reload({ onFinish: () => setChecking(false) });
                }}
            >
                {checking ? <Spinner /> : <RefreshCw />}
                {t('Tekrar kontrol et')}
            </Button>
        </div>
    );
}

function DatabaseStep({
    defaults,
    onSaved,
}: {
    defaults: Props['database'];
    onSaved: () => void;
}) {
    const form = useForm({
        host: defaults.host || '127.0.0.1',
        port: defaults.port || '3306',
        database: defaults.database,
        username: defaults.username,
        password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(databaseRoute().url, {
            preserveScroll: true,
            onSuccess: () => onSaved(),
            onFinish: () => form.reset('password'),
        });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <div>
                <h2 className="text-base font-medium">
                    {t('Veritabanı bağlantısı')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Hosting panelinizde (ör. cPanel → MySQL Veritabanları) boş bir MySQL / MariaDB veritabanı ve kullanıcısı oluşturup bilgilerini girin.',
                    )}
                </p>
            </div>

            {errors.database && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>{t('Bağlantı kurulamadı')}</AlertTitle>
                    <AlertDescription className="break-all">
                        {errors.database}
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
                <div className="grid gap-2">
                    <Label htmlFor="host">{t('Sunucu')}</Label>
                    <Input
                        id="host"
                        value={form.data.host}
                        onChange={(e) => form.setData('host', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.host} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="port">{t('Port')}</Label>
                    <Input
                        id="port"
                        inputMode="numeric"
                        value={form.data.port}
                        onChange={(e) => form.setData('port', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.port} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="database">{t('Veritabanı adı')}</Label>
                <Input
                    id="database"
                    value={form.data.database}
                    onChange={(e) => form.setData('database', e.target.value)}
                    required
                />
                <InputError message={form.errors.database} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="username">{t('Kullanıcı adı')}</Label>
                    <Input
                        id="username"
                        autoComplete="off"
                        value={form.data.username}
                        onChange={(e) =>
                            form.setData('username', e.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.username} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">{t('Şifre')}</Label>
                    <PasswordInput
                        id="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                    />
                    <InputError message={form.errors.password} />
                </div>
            </div>

            <Button
                type="submit"
                className="self-end"
                disabled={form.processing}
            >
                {form.processing ? <Spinner /> : <Database />}
                {t('Bağlan ve devam et')}
            </Button>
        </form>
    );
}

function SiteStep({ onChangeDatabase }: { onChangeDatabase: () => void }) {
    const form = useForm({
        app_name: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
        sample_data: '0',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, { preserveScroll: true });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <div>
                <h2 className="text-base font-medium">
                    {t('Site ve yönetici')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Firma adınızı ve panele girecek yönetici hesabını belirleyin. Logo ve diğer bilgiler kurulumdan sonra panelden girilir.',
                    )}
                </p>
            </div>

            {errors.install && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>{t('Kurulum tamamlanamadı')}</AlertTitle>
                    <AlertDescription className="break-all">
                        {errors.install}
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-2">
                <Label htmlFor="app_name">{t('Firma / site adı')}</Label>
                <Input
                    id="app_name"
                    value={form.data.app_name}
                    onChange={(e) => form.setData('app_name', e.target.value)}
                    required
                    autoFocus
                />
                <InputError message={form.errors.app_name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="admin_name">{t('Yönetici adı')}</Label>
                    <Input
                        id="admin_name"
                        autoComplete="name"
                        value={form.data.admin_name}
                        onChange={(e) =>
                            form.setData('admin_name', e.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.admin_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="admin_email">
                        {t('Yönetici e-postası')}
                    </Label>
                    <Input
                        id="admin_email"
                        type="email"
                        autoComplete="email"
                        value={form.data.admin_email}
                        onChange={(e) =>
                            form.setData('admin_email', e.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.admin_email} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="admin_password">{t('Şifre')}</Label>
                    <PasswordInput
                        id="admin_password"
                        autoComplete="new-password"
                        value={form.data.admin_password}
                        onChange={(e) =>
                            form.setData('admin_password', e.target.value)
                        }
                        required
                    />
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'En az 12 karakter; büyük ve küçük harf, rakam ve sembol.',
                        )}
                    </p>
                    <InputError message={form.errors.admin_password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="admin_password_confirmation">
                        {t('Şifre (tekrar)')}
                    </Label>
                    <PasswordInput
                        id="admin_password_confirmation"
                        autoComplete="new-password"
                        value={form.data.admin_password_confirmation}
                        onChange={(e) =>
                            form.setData(
                                'admin_password_confirmation',
                                e.target.value,
                            )
                        }
                        required
                    />
                </div>
            </div>

            <div className="grid gap-3">
                <Label>{t('Başlangıç verileri')}</Label>
                <RadioGroup
                    value={form.data.sample_data}
                    onValueChange={(value) =>
                        form.setData('sample_data', value)
                    }
                    className="grid gap-3 sm:grid-cols-2"
                >
                    {[
                        {
                            value: '0',
                            title: t('Boş yükle'),
                            description: t(
                                'Yalnız temel veriler: ülke, il / ilçe, para birimleri, vergiler, roller ve ayarlar.',
                            ),
                        },
                        {
                            value: '1',
                            title: t('Örnek verileri yükle'),
                            description: t(
                                'Temel verilere ek olarak örnek ürünler (kategori, marka, stok) ile örnek bayi hesabı ve sipariş; sistemi denemek için.',
                            ),
                        },
                    ].map((option) => (
                        <Label
                            key={option.value}
                            htmlFor={`sample_data_${option.value}`}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-4 font-normal',
                                form.data.sample_data === option.value &&
                                    'border-primary bg-primary/5',
                            )}
                        >
                            <RadioGroupItem
                                id={`sample_data_${option.value}`}
                                value={option.value}
                                className="mt-0.5"
                            />
                            <span className="flex flex-col gap-1">
                                <span className="font-medium">
                                    {option.title}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {option.description}
                                </span>
                            </span>
                        </Label>
                    ))}
                </RadioGroup>
                <InputError message={form.errors.sample_data} />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onChangeDatabase}
                    disabled={form.processing}
                >
                    <Database />
                    {t('Veritabanını değiştir')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner /> : <Rocket />}
                    {form.processing ? t('Kuruluyor…') : t('Kurulumu başlat')}
                </Button>
            </div>

            {form.processing && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Tablolar oluşturuluyor ve veriler yükleniyor; bu birkaç dakika sürebilir. Sayfayı kapatmayın.',
                    )}
                </p>
            )}
        </form>
    );
}

export default function Install({
    requirements,
    passes,
    database_ready,
    database,
}: Props) {
    const [editDatabase, setEditDatabase] = useState(false);
    const step = !passes ? 1 : !database_ready || editDatabase ? 2 : 3;

    return (
        <>
            <Head title={t('Kurulum')} />

            <InstallShell step={step}>
                {step === 1 && <RequirementsStep requirements={requirements} />}
                {step === 2 && (
                    <DatabaseStep
                        defaults={database}
                        onSaved={() => setEditDatabase(false)}
                    />
                )}
                {step === 3 && (
                    <SiteStep onChangeDatabase={() => setEditDatabase(true)} />
                )}
            </InstallShell>
        </>
    );
}
