import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as memberShow } from '@/routes/panel/members';
import { collections } from '@/routes/panel/shop/ledger';
import { show as orderShow } from '@/routes/panel/shop/orders';
import type { Option, Paginated } from '@/types';

type PaymentRow = {
    id: string;
    paid_at: string | null;
    account: { id: string; title: string; member_id: string | null } | null;
    order: { id: string; number: string } | null;
    method_label: string;
    amount: string;
    reference: string;
    note: string | null;
    user: string | null;
};

type Filters = { method?: string; from?: string; to?: string; q?: string };

type Props = {
    payments: Paginated<PaymentRow>;
    filters: Filters;
    methods: Option<string>[];
};

const ALL = '__all';

export default function Collections({ payments, filters, methods }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const hasFilter = Object.values(filters).some((v) => v);

    const apply = (patch: Filters) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...patch }).filter(([, v]) => v),
        );
        router.get(collections.url(), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ q, from, to });
    };

    return (
        <>
            <Head title={t('Tahsilatlar')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Tahsilatlar')}
                    meta={t(':total kayıt', { total: payments.total })}
                    description={t(
                        'Alınan tüm ödemeler: sipariş ödemeleri (kart, havale) ve cariye girilen tahsilatlar. Elle tahsilat müşteri detayının Cari sekmesinden girilir.',
                    )}
                />

                <TableCard
                    toolbar={
                        <form
                            onSubmit={submit}
                            className="flex w-full flex-col gap-2 lg:flex-row lg:items-center"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t('Hesap ünvanı ya da kodu')}
                                    className="pl-10"
                                    aria-label={t('Tahsilat ara')}
                                />
                            </div>
                            <Select
                                value={filters.method || ALL}
                                onValueChange={(v) =>
                                    apply({ method: v === ALL ? '' : v })
                                }
                            >
                                <SelectTrigger className="lg:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('Tüm yöntemler')}
                                    </SelectItem>
                                    {methods.map((m) => (
                                        <SelectItem
                                            key={m.value}
                                            value={m.value}
                                        >
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="lg:w-40"
                                aria-label={t('Başlangıç')}
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="lg:w-40"
                                aria-label={t('Bitiş')}
                            />
                            <Button type="submit" variant="secondary">
                                <Search data-icon="inline-start" />
                                {t('Filtrele')}
                            </Button>
                            {hasFilter && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setQ('');
                                        setFrom('');
                                        setTo('');
                                        router.get(collections.url());
                                    }}
                                >
                                    <RotateCcw data-icon="inline-start" />
                                    {t('Temizle')}
                                </Button>
                            )}
                        </form>
                    }
                    footer={
                        payments.data.length > 0 ? (
                            <DataPagination
                                paginator={payments}
                                label={t('kayıt')}
                            />
                        ) : undefined
                    }
                >
                    {payments.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HandCoins />
                                </EmptyMedia>
                                <EmptyTitle>{t('Tahsilat yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Ödenen siparişler ve cariye girilen tahsilatlar burada listelenir.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Tarih')}</TableHead>
                                    <TableHead>{t('Hesap')}</TableHead>
                                    <TableHead>{t('Yöntem')}</TableHead>
                                    <TableHead>{t('Belge')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Tutar')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(p.paid_at)}
                                        </TableCell>
                                        <TableCell>
                                            {p.account?.member_id ? (
                                                <Link
                                                    href={memberShow(
                                                        p.account.member_id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {p.account.title}
                                                </Link>
                                            ) : (
                                                (p.account?.title ?? '—')
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {p.method_label}
                                            {p.user && (
                                                <div className="text-xs text-muted-foreground">
                                                    {p.user}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {p.order ? (
                                                <Link
                                                    href={orderShow(p.order.id)}
                                                    className="hover:underline"
                                                >
                                                    #{p.order.number}
                                                </Link>
                                            ) : (
                                                <span>
                                                    {t('Cari tahsilat')}
                                                </span>
                                            )}
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {p.reference}
                                            </div>
                                            {p.note && (
                                                <div className="text-xs text-muted-foreground">
                                                    {p.note}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                            {p.amount}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

Collections.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Tahsilatlar'), href: collections() },
    ],
};
