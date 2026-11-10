import { router } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useState } from 'react';

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
import { Field, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { cancel as cancelRoute } from '@/routes/panel/shop/quotes';

import type { QuoteData } from './types';

/** Yönetici iptali: isteğe bağlı not müşteriye e-postayla gider. */
export function CancelDialog({ quote }: { quote: QuoteData }) {
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" className="w-full">
                    <Ban data-icon="inline-start" />
                    {t('Teklifi iptal et')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(':number iptal edilsin mi?', {
                            number: quote.number,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Müşteri artık bu teklifi onaylayamaz ya da ödeyemez. Müşteriye e-posta gider.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <Field>
                    <FieldLabel htmlFor="cancel-note">
                        {t('Müşteriye not (isteğe bağlı)')}
                    </FieldLabel>
                    <Textarea
                        id="cancel-note"
                        rows={3}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder={t('Örn. Ürün tedarik edilemiyor.')}
                    />
                </Field>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            {t('Vazgeç')}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={busy}
                        onClick={() =>
                            router.post(
                                cancelRoute.url(quote.id),
                                { note },
                                {
                                    preserveScroll: true,
                                    onStart: () => setBusy(true),
                                    onFinish: () => setBusy(false),
                                },
                            )
                        }
                    >
                        {busy ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <Ban data-icon="inline-start" />
                        )}
                        {t('İptal et')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
