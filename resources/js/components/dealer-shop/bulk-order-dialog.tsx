import { Form } from '@inertiajs/react';
import { Download, FileSpreadsheet, Upload, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import cart from '@/routes/app/shop/cart';

/**
 * Excel ile toplu sipariş: stok kodu / barkod + adet satırları dosyadan ya da
 * Excel'den yapıştırılarak sepete eklenir.
 */
export function BulkOrderDialog({ trigger }: { trigger?: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline">
                        <FileSpreadsheet data-icon="inline-start" />
                        {t('Excel ile sipariş')}
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Excel ile toplu sipariş')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'İlk sütunda stok kodu ya da barkod, ikinci sütunda adet olsun. Dosya yükleyin ya da Excel’den satırları kopyalayıp yapıştırın. En fazla 1.000 satır.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <Form
                        {...cart.import.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="flex flex-col gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="bulk-file">
                                            {t('Excel ya da CSV dosyası')}
                                        </FieldLabel>
                                        <Input
                                            id="bulk-file"
                                            name="file"
                                            type="file"
                                            accept=".xlsx,.csv,.txt"
                                        />
                                        <InputError message={errors.file} />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="bulk-text">
                                            {t('ya da satırları yapıştırın')}
                                        </FieldLabel>
                                        <Textarea
                                            id="bulk-text"
                                            name="text"
                                            rows={6}
                                            placeholder={
                                                'URUN-001\t10\n8690000000011\t5'
                                            }
                                            className="font-mono text-xs"
                                        />
                                        <InputError message={errors.text} />
                                    </Field>
                                </FieldGroup>
                                <DialogFooter className="sm:justify-between">
                                    <Button variant="ghost" asChild>
                                        <a href={cart.importTemplate().url}>
                                            <Download data-icon="inline-start" />
                                            {t('Boş şablonu indir')}
                                        </a>
                                    </Button>
                                    <div className="flex gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                <X data-icon="inline-start" />
                                                {t('Vazgeç')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Upload data-icon="inline-start" />
                                            )}
                                            {t('Sepete ekle')}
                                        </Button>
                                    </div>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export type BulkOrderResult = {
    added: number;
    failed: { code: string; quantity: number | null; reason: string }[];
};
