import { Head, Link, router } from '@inertiajs/react';
import { Plus, RotateCcw, Search, Tag } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { StatusSwitch } from '@/components/shop/form-fields';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    create,
    edit,
    index,
    status as statusRoute,
} from '@/routes/panel/shop/campaigns';
import type { Paginated, ShopOption, Status } from '@/types';

type CampaignRow = {
    id: string;
    name: string;
    action_label: string;
    summary: string;
    starts_at: string | null;
    ends_at: string | null;
    live: boolean;
    status: Status;
};

type Props = {
    campaigns: Paginated<CampaignRow>;
    filters: { q: string; status: string; action: string };
    actions: ShopOption[];
};

const STATUS_OPTIONS: ShopOption[] = [
    { value: 'active', label: t('Aktif') },
    { value: 'passive', label: t('Pasif') },
];

function Period({ row }: { row: CampaignRow }) {
    if (!row.starts_at && !row.ends_at) {
        return <span className="text-muted-foreground">{t('Süresiz')}</span>;
    }

    return (
        <span className="text-sm whitespace-nowrap">
            {row.starts_at ? formatDateTime(row.starts_at) : '…'}
            {' – '}
            {row.ends_at ? formatDateTime(row.ends_at) : '…'}
        </span>
    );
}

export default function CampaignIndex({ campaigns, filters, actions }: Props) {
    const [q, setQ] = useState(filters.q);
    const hasFilter = Boolean(filters.q || filters.status || filters.action);

    // Boş filtreler sorgu dizesine gitmez.
    const apply = (patch: Partial<Props['filters']>) => {
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

    return (
        <>
            <Head title={t('Kampanyalar')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Kampanyalar')}
                    meta={t(':count kampanya', { count: campaigns.total })}
                    description={t(
                        'Koşullar sağlanınca sepete uygulanan indirimler. Uyanlar arasından müşteriye en çok kazandıran uygulanır; birlikte kullanılabilir işaretlenen kampanyalar birleşir.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" />
                                {t('Yeni kampanya')}
                            </Link>
                        </Button>
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
                                    placeholder={t('Kampanya adı ara')}
                                    className="pl-10"
                                    aria-label={t('Kampanya ara')}
                                />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:w-[28rem]">
                                <SearchSelect
                                    aria-label={t('İşlem')}
                                    value={filters.action}
                                    onChange={(v) => apply({ action: v })}
                                    options={actions}
                                    placeholder={t('İşlem: tümü')}
                                    clearable
                                />
                                <SearchSelect
                                    aria-label={t('Durum')}
                                    value={filters.status}
                                    onChange={(v) => apply({ status: v })}
                                    options={STATUS_OPTIONS}
                                    placeholder={t('Durum: tümü')}
                                    clearable
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" variant="secondary">
                                    {t('Ara')}
                                </Button>
                                {hasFilter && (
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
                        campaigns.last_page > 1 ? (
                            <DataPagination
                                paginator={campaigns}
                                label={t('kampanya')}
                            />
                        ) : undefined
                    }
                >
                    {campaigns.data.length === 0 ? (
                        <Empty className="py-16">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Tag />
                                </EmptyMedia>
                                <EmptyTitle>{t('Kampanya yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {hasFilter
                                        ? t('Filtreye uygun kampanya yok.')
                                        : t('Henüz kampanya oluşturulmamış.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Kampanya')}</TableHead>
                                    <TableHead>{t('İşlem')}</TableHead>
                                    <TableHead>{t('Tarih aralığı')}</TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {campaigns.data.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className={cn(
                                            row.status !== 'active' &&
                                                'opacity-70',
                                        )}
                                    >
                                        <TableCell className="whitespace-normal">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={edit(row.id)}
                                                    className="font-medium hover:text-primary hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                {row.status === 'active' &&
                                                    !row.live && (
                                                        <Badge variant="outline">
                                                            {row.starts_at &&
                                                            new Date(
                                                                row.starts_at,
                                                            ) > new Date()
                                                                ? t(
                                                                      'Henüz başlamadı',
                                                                  )
                                                                : t(
                                                                      'Süresi doldu',
                                                                  )}
                                                        </Badge>
                                                    )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm">
                                                {row.summary}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.action_label}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Period row={row} />
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={row.status}
                                                url={statusRoute.url(row.id)}
                                                label={t(
                                                    'Kampanya durumunu değiştir',
                                                )}
                                            />
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

CampaignIndex.layout = {
    breadcrumbs: [{ title: t('Kampanyalar'), href: index() }],
};
