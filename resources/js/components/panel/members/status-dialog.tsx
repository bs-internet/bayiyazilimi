import { Form } from '@inertiajs/react';
import { RefreshCw, X } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import type { EditableUserStatus } from '@/types';

/** Üyenin erişim durumunu (aktif/pasif) değiştirme penceresi. */
export function StatusDialog({
    user,
    open,
    onOpenChange,
    value,
    onValueChange,
}: {
    user: MemberDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    value: EditableUserStatus;
    onValueChange: (value: EditableUserStatus) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Durum değiştir')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Üyenin erişim durumunu güncelleyin. İşlem kaydedilecek ve üyeye bilgilendirme e-postası gönderilecek.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...MembersController.updateStatus.form(user)}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="status" value={value} />
                            <div className="grid gap-2">
                                <Label>{t('Yeni durum')}</Label>
                                <Select
                                    value={value}
                                    onValueChange={(next) =>
                                        onValueChange(
                                            next as EditableUserStatus,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="active">
                                                {t('Aktif')}
                                            </SelectItem>
                                            <SelectItem value="passive">
                                                {t('Pasif')}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
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
                                    {processing ? <Spinner /> : <RefreshCw />}
                                    {t('Durumu güncelle')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
