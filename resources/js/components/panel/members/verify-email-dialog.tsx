import { Form } from '@inertiajs/react';
import { MailCheck, X } from 'lucide-react';
import MembersController from '@/actions/App/Http/Controllers/Panel/Members/MembersController';
import type { MemberDetail } from '@/components/panel/members/types';
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

/** Üyenin e-posta adresini yönetici olarak onaylama penceresi. */
export function VerifyEmailDialog({
    user,
    open,
    onOpenChange,
}: {
    user: MemberDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('E-posta adresini onayla')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            ':email adresini bu üye için onaylamak istediğinize emin misiniz? İşlem kaydedilecek ve üyeye bilgilendirme e-postası gönderilecek.',
                            { email: user.email },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...MembersController.verifyEmail.form(user)}
                    options={{
                        preserveScroll: true,
                    }}
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
                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner /> : <MailCheck />}
                                {t('E-postayı onayla')}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
