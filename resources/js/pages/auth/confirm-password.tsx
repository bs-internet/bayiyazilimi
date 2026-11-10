import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title={t('Şifreyi doğrula')} />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('Şifre')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder={t('Şifre')}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing ? <Spinner /> : <ShieldCheck />}
                                {t('Şifreyi doğrula')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: t('Şifrenizi doğrulayın'),
    description: t(
        'Burası güvenli bir alandır. Devam etmeden önce lütfen şifrenizi doğrulayın.',
    ),
};
