import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    FileText,
    MoreHorizontal,
    RotateCcw,
    Search,
    ShoppingCart,
    UserPlus,
    Users,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import {
    CustomerTypeBadge,
    MemberStatusBadge,
    dealerSummary,
    initials,
} from '@/components/panel/member-parts';
import { SearchSelect } from '@/components/search-select';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatMoney, setBaseCurrency } from '@/lib/format';
import { t } from '@/lib/i18n';
import { create, index, show } from '@/routes/panel/members';
import { index as dealerGroupsIndex } from '@/routes/panel/shop/dealer-groups';
import { index as ordersIndex } from '@/routes/panel/shop/orders';
import { index as quotesIndex } from '@/routes/panel/shop/quotes';
import type { MemberCustomerInfo, Option, Paginated, PanelUser } from '@/types';

type Filters = {
    q?: string;
    type?: string;
    group?: string;
    status?: string;
};

type Props = {
    /** Ana para birimi. */
    currency: string;
    users: Paginated<PanelUser & MemberCustomerInfo>;
    filters: Filters;
    customerTypes: Option<string>[];
    dealerGroups: Option<string>[];
    statuses: Option<string>[];
};

export default function Members({
    currency,
    users,
    filters,
    customerTypes,
    dealerGroups,
    statuses,
}: Props) {
    setBaseCurrency(currency);
    const [q, setQ] = useState(filters.q ?? '');
    const hasFilters = Object.values(filters).some((v) => v);

    // Boş filtreler sorgu dizesine gitmez.
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

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({ q });
    };

    const reset = () => {
        setQ('');
        router.get(index.url(), {}, { preserveScroll: true });
    };

    // Müşteri hesapları yalnız vitrinle gelir; yoksa liste yalnız bayilerdir
    // (tür filtresi ve tür rozeti gösterilmez).
    const customersEnabled = customerTypes.length > 1;
    const pageTitle = customersEnabled ? t('Müşteriler') : t('Bayiler');
    const typeOptions: Option<string>[] = customerTypes.map((type) => ({
        value: type.value,
        label: type.value === 'dealer' ? t('Bayi') : t('Müşteri'),
    }));
    const groupOptions: Option<string>[] = [
        { value: 'none', label: t('Grupsuz bayiler') },
        ...dealerGroups,
    ];

    return (
        <>
            <Head title={pageTitle} />

            <Page>
                <PageTitle
                    trail={[{ title: pageTitle }]}
                    title={pageTitle}
                    meta={
                        <span>{t(':total kayıt', { total: users.total })}</span>
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={dealerGroupsIndex()}>
                                    <UsersRound data-icon="inline-start" />
                                    {t('Bayi grupları')}
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={create()}>
                                    <UserPlus data-icon="inline-start" />
                                    {t('Yeni bayi')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                <TableCard
                    toolbar={
                        <form
                            onSubmit={submitSearch}
                            className="flex w-full flex-col gap-2 lg:flex-row"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t(
                                        'Ad, e-posta veya firma ara',
                                    )}
                                    className="pl-9"
                                    aria-label={t('Ara')}
                                />
                            </div>
                            <div
                                className={
                                    customersEnabled
                                        ? 'grid gap-2 sm:grid-cols-3 lg:w-[36rem]'
                                        : 'grid gap-2 sm:grid-cols-2 lg:w-[24rem]'
                                }
                            >
                                {customersEnabled ? (
                                    <SearchSelect
                                        aria-label={t('Tür')}
                                        value={filters.type ?? ''}
                                        onChange={(v) => apply({ type: v })}
                                        options={typeOptions}
                                        placeholder={t('Tür: tümü')}
                                        clearable
                                    />
                                ) : null}
                                <SearchSelect
                                    aria-label={t('Bayi grubu')}
                                    value={filters.group ?? ''}
                                    onChange={(v) => apply({ group: v })}
                                    options={groupOptions}
                                    placeholder={t('Bayi grubu: tümü')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Durum')}
                                    value={filters.status ?? ''}
                                    onChange={(v) => apply({ status: v })}
                                    options={statuses}
                                    placeholder={t('Durum: tümü')}
                                    clearable
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" variant="secondary">
                                    {t('Ara')}
                                </Button>
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={reset}
                                    >
                                        <RotateCcw data-icon="inline-start" />
                                        {t('Temizle')}
                                    </Button>
                                )}
                            </div>
                        </form>
                    }
                    footer={
                        users.last_page > 1 ? (
                            <DataPagination paginator={users} />
                        ) : undefined
                    }
                >
                    {users.data.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Users />
                                </EmptyMedia>
                                <EmptyTitle>{t('Kayıt bulunamadı')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Seçili filtreye uygun müşteri ya da bayi yok.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {customersEnabled
                                            ? t('Müşteri')
                                            : t('Bayi')}
                                    </TableHead>
                                    <TableHead>
                                        {customersEnabled
                                            ? t('Tür')
                                            : t('Bayi grubu')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Sipariş')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Toplam harcama')}
                                    </TableHead>
                                    <TableHead>{t('Son giriş')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <Link
                                                href={show(user)}
                                                className="flex min-w-0 items-center gap-3"
                                            >
                                                <Avatar className="size-9">
                                                    <AvatarFallback className="text-xs">
                                                        {initials(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium hover:underline">
                                                        {user.name}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {user.email}
                                                        {user.company &&
                                                            user.company !==
                                                                user.name &&
                                                            ` · ${user.company}`}
                                                    </div>
                                                </div>
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col items-start gap-1">
                                                {customersEnabled ||
                                                user.customer_type !==
                                                    'dealer' ? (
                                                    <CustomerTypeBadge
                                                        user={user}
                                                    />
                                                ) : null}
                                                {user.customer_type ===
                                                    'dealer' && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {dealerSummary(user)}
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {user.orders_count ?? 0}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(
                                                user.orders_total ?? 0,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(
                                                user.last_login_at as
                                                    string | null | undefined,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <MemberStatusBadge
                                                status={user.status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={t(
                                                            'İşlemler',
                                                        )}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={show(user)}>
                                                            <Eye />
                                                            {t('Görüntüle')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {user.account_id && (
                                                        <>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={ordersIndex(
                                                                        {
                                                                            query: {
                                                                                account:
                                                                                    user.account_id,
                                                                            },
                                                                        },
                                                                    )}
                                                                >
                                                                    <ShoppingCart />
                                                                    {t(
                                                                        'Siparişleri',
                                                                    )}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={quotesIndex(
                                                                        {
                                                                            query: {
                                                                                account:
                                                                                    user.account_id,
                                                                            },
                                                                        },
                                                                    )}
                                                                >
                                                                    <FileText />
                                                                    {t(
                                                                        'Teklifleri',
                                                                    )}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
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

Members.layout = {
    breadcrumbs: [
        {
            title: t('Bayiler'),
            href: index(),
        },
    ],
};
