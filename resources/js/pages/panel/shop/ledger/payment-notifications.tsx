import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, FileText, Landmark, Save, X } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-permissions';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as memberShow } from '@/routes/panel/members';
import { show as orderShow } from '@/routes/panel/shop/orders';
import {
    approve,
    index,
    receipt,
    reject,
} from '@/routes/panel/shop/payment-notifications';
import type { Option, Paginated } from '@/types';

type Row = {
    id: string;
    created_at: string | null;
    account: { id: string; title: string; member_id: string | null } | null;
    user: string | null;
    order: { id: string; number: string } | null;
    bank: string | null;
    amount: string;
    amount_formatted: string;
    transfer_date: string;
    sender_name: string | null;
    description: string | null;
    status: string;
    status_label: string;
    reject_reason: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    has_receipt: boolean;
};

type Props = {
    notifications: Paginated<Row>;
    status: string;
    statuses: Option<string>[];
    currency: string;
};

function ReviewDialog({
    row,
    mode,
    currency,
    onClose,
}: {
    row: Row;
    mode: 'approve' | 'reject';
    currency: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'approve'
                            ? t('Havale bildirimini onayla')
                            : t('Havale bildirimini reddet')}
                    </DialogTitle>
                    <DialogDescription>
                        {row.account?.title} · {row.amount_formatted} ·{' '}
                        {formatDate(row.transfer_date)}
                        {mode === 'approve' &&
                            t(
                                '. Ödeme kaydı yazılır ve cariye alacak olarak işlenir; bağlı sipariş havale bekliyorsa ve tutar karşılıyorsa sipariş "Ödendi" olur.',
                            )}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...(mode === 'approve'
                        ? approve.form(row.id)
                        : reject.form(row.id))}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {mode === 'approve' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="approve_amount">
                                        {t('Hesaba geçen tutar (:currency)', {
                                            currency,
                                        })}
                                    </Label>
                                    <Input
                                        id="approve_amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        defaultValue={row.amount}
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="reject_reason">
                                        {t('Ret gerekçesi')}
                                    </Label>
                                    <Textarea
                                        id="reject_reason"
                                        name="reason"
                                        rows={2}
                                        maxLength={255}
                                        required
                                        placeholder={t(
                                            'Ör. hesaba ulaşmadı, tutar uyuşmuyor',
                                        )}
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                            )}
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
                                    variant={
                                        mode === 'approve'
                                            ? 'default'
                                            : 'destructive'
                                    }
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : mode === 'approve' ? (
                                        <Save />
                                    ) : (
                                        <X />
                                    )}
                                    {mode === 'approve'
                                        ? t('Onayla')
                                        : t('Reddet')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function PaymentNotifications({
    notifications,
    status,
    statuses,
    currency,
}: Props) {
    const canApprove = useCan('panel.shop.payment-notifications.approve');
    const canReject = useCan('panel.shop.payment-notifications.reject');
    const canReceipt = useCan('panel.shop.payment-notifications.receipt');
    const [review, setReview] = useState<{
        row: Row;
        mode: 'approve' | 'reject';
    } | null>(null);

    return (
        <>
            <Head title={t('Havale Bildirimleri')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Havale Bildirimleri')}
                    meta={t(':total kayıt', { total: notifications.total })}
                    description={t(
                        'Bayilerin dekontla bildirdiği havale / EFT ödemeleri. Onaylanan bildirim ödeme kaydı olarak cariye işlenir.',
                    )}
                />

                <TableCard
                    toolbar={
                        <Select
                            value={status}
                            onValueChange={(v) =>
                                router.get(
                                    index.url(),
                                    { status: v },
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            <SelectTrigger className="sm:w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                                <SelectItem value="all">{t('Tümü')}</SelectItem>
                            </SelectContent>
                        </Select>
                    }
                    footer={
                        notifications.data.length > 0 ? (
                            <DataPagination
                                paginator={notifications}
                                label={t('kayıt')}
                            />
                        ) : undefined
                    }
                >
                    {notifications.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Landmark />
                                </EmptyMedia>
                                <EmptyTitle>{t('Bildirim yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Bayiler Cari Hesabım ekranından havale bildirimi yaptığında burada listelenir.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Müşteri')}</TableHead>
                                    <TableHead>{t('Havale')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Tutar')}
                                    </TableHead>
                                    <TableHead className="w-40">
                                        <span className="sr-only">
                                            {t('İşlem')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifications.data.map((n) => (
                                    <TableRow key={n.id}>
                                        <TableCell>
                                            {n.account?.member_id ? (
                                                <Link
                                                    href={memberShow(
                                                        n.account.member_id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {n.account.title}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {n.account?.title ?? '—'}
                                                </span>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {n.user} ·{' '}
                                                {formatDateTime(n.created_at)}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            <div>
                                                {formatDate(n.transfer_date)}
                                                {n.bank && ` · ${n.bank}`}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {n.sender_name}
                                                {n.order && (
                                                    <>
                                                        {n.sender_name
                                                            ? ' · '
                                                            : ''}
                                                        <Link
                                                            href={orderShow(
                                                                n.order.id,
                                                            )}
                                                            className="hover:underline"
                                                        >
                                                            #{n.order.number}
                                                        </Link>
                                                    </>
                                                )}
                                            </div>
                                            {n.description && (
                                                <div className="text-xs text-muted-foreground">
                                                    {n.description}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    n.status === 'approved'
                                                        ? 'default'
                                                        : n.status ===
                                                            'rejected'
                                                          ? 'destructive'
                                                          : 'outline'
                                                }
                                            >
                                                {n.status_label}
                                            </Badge>
                                            {(n.reviewer ||
                                                n.reject_reason) && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {n.reviewer}
                                                    {n.reject_reason &&
                                                        ` · ${n.reject_reason}`}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                            {n.amount_formatted}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                {n.has_receipt &&
                                                    canReceipt && (
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8"
                                                            asChild
                                                        >
                                                            <a
                                                                href={receipt.url(
                                                                    n.id,
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                aria-label={t(
                                                                    'Dekontu aç',
                                                                )}
                                                            >
                                                                <FileText />
                                                            </a>
                                                        </Button>
                                                    )}
                                                {n.status === 'pending' &&
                                                    canApprove && (
                                                        <Button
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={t(
                                                                'Onayla',
                                                            )}
                                                            onClick={() =>
                                                                setReview({
                                                                    row: n,
                                                                    mode: 'approve',
                                                                })
                                                            }
                                                        >
                                                            <Check />
                                                        </Button>
                                                    )}
                                                {n.status === 'pending' &&
                                                    canReject && (
                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            className="size-8"
                                                            aria-label={t(
                                                                'Reddet',
                                                            )}
                                                            onClick={() =>
                                                                setReview({
                                                                    row: n,
                                                                    mode: 'reject',
                                                                })
                                                            }
                                                        >
                                                            <X />
                                                        </Button>
                                                    )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>

            {review && (
                <ReviewDialog
                    row={review.row}
                    mode={review.mode}
                    currency={currency}
                    onClose={() => setReview(null)}
                />
            )}
        </>
    );
}

PaymentNotifications.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Havale Bildirimleri'), href: index() },
    ],
};
