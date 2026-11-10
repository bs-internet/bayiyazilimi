import { Form, Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { ContractAgreementLabel } from '@/components/contract-agreement';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { ContractInfo } from '@/types';

type Props = {
    /** Üyelik formunda onaylatılan sözleşmeler; boşsa onay kutusu yok. */
    contracts: ContractInfo[];
};

export default function Register({ contracts }: Props) {
    return (
        <>
            <Head title={t('Kayıt ol')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Ad soyad')}</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder={t('Ad soyad')}
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('E-posta adresi')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder={t('eposta@ornek.com')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">{t('Şifre')}</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={t('Şifre')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('Şifre tekrarı')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={t('Şifre tekrarı')}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            {contracts.length > 0 ? (
                                <div className="grid gap-2">
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="agreement"
                                            name="agreement"
                                            value="1"
                                            required
                                            className="mt-0.5"
                                        />
                                        <Label
                                            htmlFor="agreement"
                                            className="block leading-snug font-normal"
                                        >
                                            <ContractAgreementLabel
                                                contracts={contracts}
                                            />
                                        </Label>
                                    </div>
                                    <InputError message={errors.agreement} />
                                </div>
                            ) : null}

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing ? <Spinner /> : <UserPlus />}
                                {t('Hesap oluştur')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {t('Zaten hesabınız var mı?')}{' '}
                            <TextLink href={login()} tabIndex={6}>
                                {t('Giriş yap')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: t('Hesap oluştur'),
    description: t('Hesabınızı oluşturmak için bilgilerinizi girin'),
};
