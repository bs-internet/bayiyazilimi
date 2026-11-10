import { Form } from '@inertiajs/react';
import { Send, X } from 'lucide-react';
import MembersController from '@/actions/App/Http/Controllers/Panel/Members/MembersController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

/** Üyenin e-posta adresini değiştirme (onay bağlantısı) penceresi. */
export function EmailChangeDialog({
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
                    <DialogTitle>{t('E-posta adresini değiştir')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Yeni adrese onay bağlantısı gönderilecek. Bağlantı onaylandığında e-posta değişecek, yeni adres onaylanacak ve üyenin oturumları kapatılacak.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...MembersController.requestEmailChange.form(user)}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={['email']}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_email">
                                    {t('Var olan e-posta adresi')}
                                </Label>
                                <Input
                                    id="current_email"
                                    type="email"
                                    value={user.email}
                                    disabled
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="new_email">
                                    {t('Yeni e-posta adresi')}
                                </Label>
                                <Input
                                    id="new_email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="email"
                                    placeholder={t('yeni@ornek.com')}
                                    aria-invalid={Boolean(errors.email)}
                                />
                                <InputError message={errors.email} />
                            </div>

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
                                    {processing ? <Spinner /> : <Send />}
                                    {t('Onay bağlantısı gönder')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
