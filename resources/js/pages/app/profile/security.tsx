import { Form, Head } from '@inertiajs/react';
import { Save, ShieldCheck, ShieldOff, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/App/Profile/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { t } from '@/lib/i18n';
import { security } from '@/routes/app/profile';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function Security({
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const [showDisableModal, setShowDisableModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    return (
        <>
            <Head title={t('Güvenlik')} />

            <h1 className="sr-only">{t('Güvenlik')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Şifreyi güncelle')}
                    description={t(
                        'Hesabınızı güvende tutmak için güçlü ve benzersiz bir şifre kullanın.',
                    )}
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="w-full max-w-md space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    {t('Mevcut şifre')}
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder={t('Mevcut şifre')}
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('Yeni şifre')}
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t('Yeni şifre')}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('Yeni şifre tekrar')}
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t('Yeni şifre tekrar')}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    <Save />
                                    {t('Şifreyi kaydet')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {canManageTwoFactor && (
                <div className="max-w-xl space-y-6">
                    <Heading
                        variant="small"
                        title={t('İki aşamalı doğrulama')}
                        description={t(
                            'Hesabınız için iki aşamalı doğrulama ayarlarını yönetin.',
                        )}
                    />
                    {twoFactorEnabled ? (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Giriş sırasında doğrulama uygulamanızdan alacağınız tek kullanımlık kod istenir.',
                                )}
                            </p>

                            <AlertDialog
                                open={showDisableModal}
                                onOpenChange={setShowDisableModal}
                            >
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        <ShieldOff />
                                        {t('2FA kapat')}
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            {t(
                                                'İki aşamalı doğrulama kapatılsın mı?',
                                            )}
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            {t(
                                                'Bu işlem hesabınızdan girişte doğrulama kodu istemeyi kaldırır. Şifre doğrulama süreniz dolduysa sistem sizi doğrulama sayfasına yönlendirebilir.',
                                            )}
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <Form
                                        {...disable.form()}
                                        onSuccess={() =>
                                            setShowDisableModal(false)
                                        }
                                    >
                                        {({ processing }) => (
                                            <AlertDialogFooter>
                                                <AlertDialogCancel
                                                    disabled={processing}
                                                >
                                                    <X />
                                                    {t('Vazgeç')}
                                                </AlertDialogCancel>
                                                <Button
                                                    variant="destructive"
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    <ShieldOff />
                                                    {t('2FA kapat')}
                                                </Button>
                                            </AlertDialogFooter>
                                        )}
                                    </Form>
                                </AlertDialogContent>
                            </AlertDialog>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'İki aşamalı doğrulamayı açtığınızda giriş sırasında doğrulama uygulamanızdaki kodu girmeniz gerekir.',
                                )}
                            </p>

                            <div>
                                {hasSetupData ? (
                                    <Button
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck />
                                        {t('Kuruluma devam et')}
                                    </Button>
                                ) : (
                                    <Form
                                        {...enable.form()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <ShieldCheck />
                                                {t('2FA aç')}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={errors}
                    />
                </div>
            )}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: t('Güvenlik'),
            href: security(),
        },
    ],
};
