import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw, Search, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { BalanceText } from '@/components/ledger/ledger-statement';
import { Badge } from '@/components/ui/badge';
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
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as memberShow } from '@/routes/panel/members';
import { index } from '@/routes/panel/shop/ledger';
import type { LedgerBalance, Option, Paginated } from '@/types';

type AccountRow = {
    id: string;
    code: string;
    title: string;
    type: string | null;
    type_label: string | null;
    group: string | null;
    member_id: string | null;
    balance: LedgerBalance;
    last_transaction_at: string | null;
};

type Filters = { q?: string; side?: string; type?: string };

type Props = {
    accounts: Paginated<AccountRow>;
    filters: Filters;
    types: Option<string>[];
    totals: { receivable: string; net: LedgerBalance };
};

const ALL = '__all';

export default function LedgerIndex({
    accounts,
    filters,
    types,
    totals,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');
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

    return (
        <>
            <Head title={t('Cari Durum')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Bayiler') }]}
                    title={t('Cari Durum')}
                    meta={t(':total hesap', { total: accounts.total })}
                    description={t(
                        'Müşteri ve bayilerin cari bakiyeleri (borçlu: müşteri borçlu). Toplam alacağınız: :receivable.',
                        { receivable: totals.receivable },
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
                                        'Ünvan, hesap kodu, üye adı ya da e-posta',
                                    )}
                                    className="pl-10"
                                    aria-label={t('Cari ara')}
                                />
                            </div>
                            <Select
                                value={filters.side || ALL}
                                onValueChange={(v) =>
                                    apply({ side: v === ALL ? '' : v })
                                }
                            >
                                <SelectTrigger className="lg:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('Hareketi olanlar')}
                                    </SelectItem>
                                    <SelectItem value="debit">
                                        {t('Borçlular')}
                                    </SelectItem>
                                    <SelectItem value="credit">
                                        {t('Alacaklılar')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {/* Hesap türü filtresi yalnız müşteri hesapları varken (vitrin). */}
                            {types.length > 1 ? (
                                <Select
                                    value={filters.type || ALL}
                                    onValueChange={(v) =>
                                        apply({ type: v === ALL ? '' : v })
                                    }
                                >
                                    <SelectTrigger className="lg:w-44">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {t('Tüm hesaplar')}
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
                            ) : null}
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
                        accounts.data.length > 0 ? (
                            <DataPagination
                                paginator={accounts}
                                label={t('hesap')}
                            />
                        ) : undefined
                    }
                >
                    {accounts.data.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Wallet />
                                </EmptyMedia>
                                <EmptyTitle>{t('Cari hareket yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Siparişler hazırlanmaya alındığında ve tahsilat girildiğinde hesaplar burada listelenir.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Hesap')}</TableHead>
                                    <TableHead>{t('Tür')}</TableHead>
                                    <TableHead>{t('Son hareket')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Bakiye')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {accounts.data.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell>
                                            {a.member_id ? (
                                                <Link
                                                    href={memberShow(
                                                        a.member_id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {a.title}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {a.title}
                                                </span>
                                            )}
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {a.code}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {a.type_label ?? '—'}
                                            </Badge>
                                            {a.group && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {a.group}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {a.last_transaction_at
                                                ? formatDate(
                                                      a.last_transaction_at,
                                                  )
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold whitespace-nowrap">
                                            <BalanceText balance={a.balance} />
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

LedgerIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Cari Durum'), href: index() },
    ],
};
