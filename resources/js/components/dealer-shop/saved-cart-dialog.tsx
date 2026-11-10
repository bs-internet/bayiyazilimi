import { Form } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import savedCarts from '@/routes/app/shop/saved-carts';

/**
 * Kayıtlı sepet adı / notu: yeni kayıt (sepeti kaydet) ya da yeniden adlandırma.
 */
export function SavedCartDialog({
    trigger,
    savedCart,
}: {
    trigger: ReactNode;
    savedCart?: { id: string; name: string; note: string | null };
}) {
    const [open, setOpen] = useState(false);
    const action = savedCart
        ? savedCarts.update.form(savedCart.id)
        : savedCarts.store.form();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {savedCart
                            ? t('Kayıtlı sepeti düzenle')
                            : t('Sepeti kaydet')}
                    </DialogTitle>
                    <DialogDescription>
                        {savedCart
                            ? t(
                                  'Sepetin adını ve notunu değiştirin ya da sepeti silin.',
                              )
                            : t(
                                  'Sepetinizdeki ürünler ve adetler bu adla saklanır; daha sonra tek tıkla sepete yükleyip siparişe dönüştürebilirsiniz.',
                              )}
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <Form
                        {...action}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="flex flex-col gap-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FieldGroup>
                                    <Field data-invalid={Boolean(errors.name)}>
                                        <FieldLabel htmlFor="saved-cart-name">
                                            {t('Sepet adı')}
                                        </FieldLabel>
                                        <Input
                                            id="saved-cart-name"
                                            name="name"
                                            defaultValue={savedCart?.name ?? ''}
                                            placeholder={t(
                                                'Örn. Aylık sarf malzemesi',
                                            )}
                                            required
                                            maxLength={120}
                                            autoFocus
                                        />
                                        <InputError message={errors.name} />
                                    </Field>
                                    <Field data-invalid={Boolean(errors.note)}>
                                        <FieldLabel htmlFor="saved-cart-note">
                                            {t('Not (isteğe bağlı)')}
                                        </FieldLabel>
                                        <Textarea
                                            id="saved-cart-note"
                                            name="note"
                                            rows={2}
                                            defaultValue={savedCart?.note ?? ''}
                                        />
                                        <InputError message={errors.note} />
                                    </Field>
                                </FieldGroup>
                                <FormActions
                                    destroy={
                                        savedCart && (
                                            <DeleteButton
                                                action={savedCarts.destroy(
                                                    savedCart.id,
                                                )}
                                                title={t(
                                                    '":saved_cart_name" silinsin mi?',
                                                    {
                                                        saved_cart_name:
                                                            savedCart.name,
                                                    },
                                                )}
                                                description={t(
                                                    'Kayıtlı sepet kalıcı olarak silinir; mevcut sepetiniz etkilenmez.',
                                                )}
                                            />
                                        )
                                    }
                                >
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            <X data-icon="inline-start" />
                                            {t('Vazgeç')}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        <Save data-icon="inline-start" />
                                        {t('Kaydet')}
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
