import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    MessageSquareMore,
    Undo2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

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
import { Field, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Müşterinin teklif üzerindeki işlemleri (sunucu izin verdiklerinin URL'si gelir). */
export type QuoteActions = {
    approve: string | null;
    revision: string | null;
    reject: string | null;
    withdraw: string | null;
    checkout: string | null;
};

export type QuoteHistoryEntry = {
    id: string;
    label: string;
    note: string | null;
    by_customer: boolean;
    created_at: string | null;
};

/** Not alanlı onay penceresi (revize: not zorunlu). */
function ActionDialog({
    trigger,
    title,
    description,
    url,
    confirmLabel,
    confirmVariant = 'default',
    noteLabel,
    notePlaceholder,
    noteRequired = false,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    url: string;
    confirmLabel: string;
    confirmVariant?: 'default' | 'destructive';
    noteLabel: string;
    notePlaceholder?: string;
    noteRequired?: boolean;
}) {
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [busy, setBusy] = useState(false);

    const submit = () =>
        router.post(
            url,
            { note },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (errors) => setError(errors.note),
            },
        );

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Field data-invalid={Boolean(error)}>
                    <FieldLabel htmlFor="quote-action-note">
                        {noteLabel}
                    </FieldLabel>
                    <Textarea
                        id="quote-action-note"
                        rows={4}
                        value={note}
                        placeholder={notePlaceholder}
                        onChange={(e) => setNote(e.target.value)}
                    />
                    <InputError message={error} />
                </Field>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            {t('Vazgeç')}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={confirmVariant}
                        disabled={busy || (noteRequired && note.trim() === '')}
                        onClick={submit}
                    >
                        {busy ? <Spinner data-icon="inline-start" /> : null}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Teklif işlem düğmeleri: Onayla (→ ödeme), Revize iste, Reddet; fiyatlanmamış
 * talepte Geri çek; onaylanmış teklifte Ödemeye geç.
 */
export function QuoteActionButtons({
    actions,
    className,
}: {
    actions: QuoteActions;
    className?: string;
}) {
    const any = Object.values(actions).some(Boolean);

    if (!any) {
        return null;
    }

    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            {actions.checkout ? (
                <Button asChild>
                    <a href={actions.checkout}>
                        <CreditCard data-icon="inline-start" />
                        {t('Ödemeye geç')}
                    </a>
                </Button>
            ) : null}
            {actions.approve ? (
                <ActionDialog
                    trigger={
                        <Button type="button">
                            <CheckCircle2 data-icon="inline-start" />
                            {t('Onayla')}
                        </Button>
                    }
                    title={t('Teklifi onaylıyor musunuz?')}
                    description={t(
                        'Onayladıktan sonra ödeme sayfasına geçersiniz; ödeme ile siparişiniz oluşur.',
                    )}
                    url={actions.approve}
                    confirmLabel={t('Onayla ve ödemeye geç')}
                    noteLabel={t('Not (isteğe bağlı)')}
                />
            ) : null}
            {actions.revision ? (
                <ActionDialog
                    trigger={
                        <Button type="button" variant="outline">
                            <MessageSquareMore data-icon="inline-start" />
                            {t('Revize iste')}
                        </Button>
                    }
                    title={t('Teklifte neyin değişmesini istersiniz?')}
                    description={t(
                        'Notunuz satış ekibimize iletilir; güncel teklif size e-postayla gönderilir.',
                    )}
                    url={actions.revision}
                    confirmLabel={t('Revize iste')}
                    noteLabel={t('İsteğiniz')}
                    notePlaceholder={t(
                        'Örn. 20 adet yerine 50 adet için fiyat rica ederiz.',
                    )}
                    noteRequired
                />
            ) : null}
            {actions.reject ? (
                <ActionDialog
                    trigger={
                        <Button type="button" variant="ghost">
                            <XCircle data-icon="inline-start" />
                            {t('Reddet')}
                        </Button>
                    }
                    title={t('Teklifi reddediyor musunuz?')}
                    description={t(
                        'Teklif kapanır. Yeni fiyat için revize isteyebilir ya da yeni talep açabilirsiniz.',
                    )}
                    url={actions.reject}
                    confirmLabel={t('Reddet')}
                    confirmVariant="destructive"
                    noteLabel={t('Neden (isteğe bağlı)')}
                />
            ) : null}
            {actions.withdraw ? (
                <ActionDialog
                    trigger={
                        <Button type="button" variant="outline">
                            <Undo2 data-icon="inline-start" />
                            {t('Talebi geri çek')}
                        </Button>
                    }
                    title={t('Teklif talebinizi geri çekiyor musunuz?')}
                    description={t('Talebiniz iptal edilir.')}
                    url={actions.withdraw}
                    confirmLabel={t('Geri çek')}
                    confirmVariant="destructive"
                    noteLabel={t('Not (isteğe bağlı)')}
                />
            ) : null}
        </div>
    );
}

/** Teklif geçmişi: talep, gönderim, revize isteği, onay … ve notlar. */
export function QuoteHistoryList({
    history,
}: {
    history: QuoteHistoryEntry[];
}) {
    if (history.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">{t('Kayıt yok.')}</p>
        );
    }

    return (
        <ol className="flex flex-col gap-4">
            {history.map((h) => (
                <li key={h.id} className="relative pl-5">
                    <span
                        className={cn(
                            'absolute top-1.5 left-0 size-2 rounded-full',
                            h.by_customer ? 'bg-chart-3' : 'bg-primary',
                        )}
                    />
                    <div className="text-sm font-medium">{h.label}</div>
                    <div className="text-xs text-muted-foreground">
                        {formatDateTime(h.created_at)}
                    </div>
                    {h.note ? (
                        <p className="mt-1 rounded-md bg-muted px-2.5 py-1.5 text-sm whitespace-pre-line">
                            {h.note}
                        </p>
                    ) : null}
                </li>
            ))}
        </ol>
    );
}
