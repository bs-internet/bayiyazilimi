import { Form, Head, Link, router } from '@inertiajs/react';
import { FileText, RefreshCw, RotateCcw, Save, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

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
import { useCan } from '@/hooks/use-permissions';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as memberShow } from '@/routes/panel/members';
import { image, index, status } from '@/routes/panel/shop/cheques';
import type { Option, Paginated } from '@/types';

type ChequeRow = {
    id: string;
    type: string;
    type_label: string;
    serial_no: string;
    bank_name: string | null;
    branch: string | null;
    drawer: string | null;
    amount: string;
    due_date: string;
    days: number;
    soon: boolean;
    status: string;
    status_label: string;
    next: Option<string>[];
    endorsed_to: string | null;
    notes: string | null;
    account: { id: string; title: string; member_id: string | null } | null;
    has_image: boolean;
};

type Filters = { status?: string; type?: string; due?: string; q?: string };

type Props = {
    cheques: Paginated<ChequeRow>;
    filters: Filters;
    statuses: Option<string>[];
    types: Option<string>[];
    totals: Record<string, string>;
    warningDays: number;
};

const ALL = '__all';

function StatusDialog({
    cheque,
    onClose,
}: {
    cheque: ChequeRow;
    onClose: () => void;
}) {
    const [next, setNext] = useState(cheque.next[0]?.value ?? '');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Durum değiştir')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            ':type_label :serial_no · :amount . Karşılıksız ya da iade edilince tutar müşterinin carisine borç yazılır.',
                            {
                                type_label: cheque.type_label,
                                serial_no: cheque.serial_no,
                                amount: cheque.amount,
                            },
                        )}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...status.form(cheque.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="status" value={next} />
                            <div className="grid gap-2">
                                <Label>{t('Yeni durum')}</Label>
                                <Select value={next} onValueChange={setNext}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {cheque.next.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                            {next === 'endorsed' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="endorsed_to">
                                        {t('Ciro edilen')}
                                    </Label>
                                    <Input
                                        id="endorsed_to"
                                        name="endorsed_to"
                                        maxLength={255}
                                        required
                                    />
                                    <InputError message={errors.endorsed_to} />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="cheque_note">{t('Not')}</Label>
                                <Input
                                    id="cheque_note"
                                    name="note"
                                    maxLength={255}
                                />
                                <InputError message={errors.note} />
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
                                    {processing ? <Spinner /> : <Save />}
                                    {t('Kaydet')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Cheques({
    cheques,
    filters,
    statuses,
    types,
    totals,
    warningDays,
}: Props) {
    const canStatus = useCan('panel.shop.cheques.status');
    const canImage = useCan('panel.shop.cheques.image');
    const [q, setQ] = useState(filters.q ?? '');
    const [editing, setEditing] = useState<ChequeRow | null>(null);
    const hasFilter = Object.values(filters).some((v) => v);

    const apply = (patch: Filters) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...patch }).filter(([, v]) => v),
        );
        router.get(index.url(), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ q });
    };

    const summary = [
        ['portfolio', t('Portföyde')],
        ['deposited', 'Tahsilde'],
        ['endorsed', t('Ciro edilen')],
    ]
        .filter(([key]) => totals[key])
        .map(([key, label]) => `${label}: ${totals[key]}`)
        .join(' · ');

    return (
        <>
            <Head title={t('Çek / Senet')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Çek / Senet')}
                    meta={t(':total kayıt', { total: cheques.total })}
                    description={t(
                        'Müşterilerden alınan çek ve senetler. Yeni çek / senet müşteri detayındaki "Tahsilat ekle" ile girilir. :summary',
                        { summary },
                    )}
                />

                <TableCard
                    toolbar={
                        <form
                            onSubmit={submit}
                            className="flex w-full flex-col gap-2 lg:flex-row"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t(
                                        'Seri no, keşideci ya da müşteri',
                                    )}
                                    className="pl-10"
                                    aria-label={t('Çek / senet ara')}
                                />
                            </div>
                            <Select
                                value={filters.status || ALL}
                                onValueChange={(v) =>
                                    apply({ status: v === ALL ? '' : v })
                                }
                            >
                                <SelectTrigger className="lg:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('Açık (portföy, tahsil, ciro)')}
                                    </SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.type || ALL}
                                onValueChange={(v) =>
                                    apply({ type: v === ALL ? '' : v })
                                }
                            >
                                <SelectTrigger className="lg:w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('Tümü')}
                                    </SelectItem>
                                    {types.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                variant={
                                    filters.due === 'soon'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    apply({
                                        due:
                                            filters.due === 'soon'
                                                ? ''
                                                : 'soon',
                                    })
                                }
                            >
                                {t('Vadesi yaklaşan (:warning_days gün)', {
                                    warning_days: warningDays,
                                })}
                            </Button>
                            <Button type="submit" variant="secondary">
                                <Search data-icon="inline-start" />
                                {t('Ara')}
                            </Button>
                            {hasFilter && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setQ('');
                                        router.get(index.url());
                                    }}
                                >
                                    <RotateCcw data-icon="inline-start" />
                                    {t('Temizle')}
                                </Button>
                            )}
                        </form>
                    }
                    footer={
                        cheques.data.length > 0 ? (
                            <DataPagination
                                paginator={cheques}
                                label={t('kayıt')}
                            />
                        ) : undefined
                    }
                >
                    {cheques.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText />
                                </EmptyMedia>
                                <EmptyTitle>{t('Çek / senet yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Müşteri detayının Cari sekmesinde "Tahsilat ekle" → Çek / Senet ile girilir.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Vade')}</TableHead>
                                    <TableHead>{t('Çek / senet')}</TableHead>
                                    <TableHead>{t('Müşteri')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Tutar')}
                                    </TableHead>
                                    <TableHead className="w-24">
                                        <span className="sr-only">
                                            {t('İşlem')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cheques.data.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell className="whitespace-nowrap">
                                            <div className="tabular-nums">
                                                {formatDate(c.due_date)}
                                            </div>
                                            {c.soon && (
                                                <Badge
                                                    variant={
                                                        c.days < 0
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                    className="mt-1"
                                                >
                                                    {c.days < 0
                                                        ? t(':days gün geçti', {
                                                              days: Math.abs(
                                                                  c.days,
                                                              ),
                                                          })
                                                        : c.days === 0
                                                          ? t('Bugün')
                                                          : t(
                                                                ':days gün kaldı',
                                                                {
                                                                    days: c.days,
                                                                },
                                                            )}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {c.type_label}{' '}
                                                <span className="font-mono">
                                                    {c.serial_no}
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {[
                                                    c.bank_name,
                                                    c.branch,
                                                    c.drawer,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                            {c.notes && (
                                                <div className="text-xs text-muted-foreground">
                                                    {c.notes}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {c.account?.member_id ? (
                                                <Link
                                                    href={memberShow(
                                                        c.account.member_id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {c.account.title}
                                                </Link>
                                            ) : (
                                                (c.account?.title ?? '—')
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    c.status === 'collected'
                                                        ? 'default'
                                                        : c.status ===
                                                                'bounced' ||
                                                            c.status ===
                                                                'returned'
                                                          ? 'destructive'
                                                          : 'outline'
                                                }
                                            >
                                                {c.status_label}
                                            </Badge>
                                            {c.endorsed_to && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    → {c.endorsed_to}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                            {c.amount}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                {c.has_image && canImage && (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8"
                                                        asChild
                                                    >
                                                        <a
                                                            href={image.url(
                                                                c.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={t(
                                                                'Görseli aç',
                                                            )}
                                                        >
                                                            <FileText />
                                                        </a>
                                                    </Button>
                                                )}
                                                {c.next.length > 0 &&
                                                    canStatus && (
                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            className="size-8"
                                                            aria-label={t(
                                                                'Durum değiştir',
                                                            )}
                                                            onClick={() =>
                                                                setEditing(c)
                                                            }
                                                        >
                                                            <RefreshCw />
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

            {editing && (
                <StatusDialog
                    cheque={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

Cheques.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Çek / Senet'), href: index() },
    ],
};
