// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { email } from '@/routes/password';

const statusMessages: Record<string, string> = {
    'We have emailed your password reset link.': t(
        'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.',
    ),
};

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title={t('Şifremi unuttum')} />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {statusMessages[status] ?? status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('E-posta adresi')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder={t('eposta@ornek.com')}
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing ? (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Send />
                                    )}
                                    {t('Şifre sıfırlama bağlantısı gönder')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>{t('Ya da')}</span>
                    <TextLink href={login()}>
                        {t('giriş ekranına dönün')}
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: t('Şifremi unuttum'),
    description: t(
        'Şifre sıfırlama bağlantısı almak için e-posta adresinizi girin',
    ),
};
