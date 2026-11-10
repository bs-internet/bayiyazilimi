import type { Method } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type RouteAction = { url: string; method: Method };

type ConfirmDeleteProps = {
    action: RouteAction;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmIcon?: ComponentType<{ className?: string }>;
    preserveScroll?: boolean;
    /** Silme başarılı olunca (ör. düzenleme sheet'ini kapatmak için). */
    onSuccess?: () => void;
    children: ReactNode;
};

export function ConfirmDelete({
    action,
    title,
    description,
    confirmLabel = t('Sil'),
    cancelLabel = t('Vazgeç'),
    confirmIcon: ConfirmIcon = Trash2,
    preserveScroll = true,
    onSuccess,
    children,
}: ConfirmDeleteProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = (event: React.MouseEvent<HTMLButtonElement>) => {
        event.preventDefault();
        setProcessing(true);

        router.visit(action.url, {
            method: action.method,
            preserveScroll,
            onSuccess: () => onSuccess?.(),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>{children}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && (
                        <AlertDialogDescription>
                            {description}
                        </AlertDialogDescription>
                    )}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        <X />
                        {cancelLabel}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleConfirm}
                        disabled={processing}
                        className="bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/30"
                    >
                        <ConfirmIcon />
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type DeleteButtonProps = Omit<ConfirmDeleteProps, 'children'> & {
    label?: string;
    disabled?: boolean;
    className?: string;
};

/**
 * Düzenleme ekranlarının standart silme düğmesi: kırmızı "Sil", onay penceresiyle.
 * Tablolarda kullanılmaz (yanlışlıkla basılmasın); düzenleme formunun altında,
 * kaydet / vazgeç düğmelerinden ayrı (solda) durur — bkz. FormActions.
 */
export function DeleteButton({
    label = t('Sil'),
    disabled,
    className,
    ...props
}: DeleteButtonProps) {
    return (
        <ConfirmDelete {...props}>
            <Button
                type="button"
                variant="destructive"
                disabled={disabled}
                className={className}
            >
                <Trash2 data-icon="inline-start" />
                {label}
            </Button>
        </ConfirmDelete>
    );
}

/**
 * Düzenleme formu düğme satırı: silme solda, diğer düğmeler sağda.
 */
export function FormActions({
    destroy,
    children,
    className,
}: {
    destroy?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3',
                className,
            )}
        >
            <div>{destroy}</div>
            <div className="flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}
