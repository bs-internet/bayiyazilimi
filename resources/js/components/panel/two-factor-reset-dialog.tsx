import { Form } from '@inertiajs/react';
import { ShieldOff, X } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

/**
 * Başka bir kullanıcının (panel kullanıcısı ya da üye) iki adımlı doğrulamasını
 * sıfırlama onayı. `form`: ilgili controller'ın `resetTwoFactor.form(user)` çıktısı.
 */
export function TwoFactorResetDialog({
    name,
    form,
    open,
    onOpenChange,
}: {
    name: string;
    form: Pick<ComponentProps<typeof Form>, 'action' | 'method'>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('İki adımlı doğrulamayı sıfırla')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            ':name kullanıcısının doğrulama uygulaması bağlantısı ve kurtarma kodları silinecek. Kullanıcı yalnız şifresiyle giriş yapıp iki adımlı doğrulamayı yeniden kurabilir. İşlem kaydedilir.',
                            { name },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <X />
                                    {t('Vazgeç')}
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : <ShieldOff />}
                                {t('Sıfırla')}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
