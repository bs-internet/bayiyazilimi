import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    Check,
    Clock,
    FileSpreadsheet,
    FileText,
    Package,
    PackageX,
    Percent,
    Receipt,
    Repeat,
    RotateCcw,
    ShoppingCart,
    Tag,
    TicketPercent,
    TrendingUp,
    TriangleAlert,
    Truck,
    Undo2,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';

import { Page, TableCard } from '@/components/admin/page';
import { KpiTile } from '@/components/dashboard/kpi-tile';
import type { KpiTone } from '@/components/dashboard/kpi-tile';
import { DataPagination } from '@/components/data-pagination';
import { SearchSelect } from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toNumber } from '@/lib/decimal';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    setBaseCurrency,
} from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index } from '@/routes/panel/shop/reports';
import type { Paginated } from '@/types';

type ValueType = 'text' | 'number' | 'money' | 'percent' | 'date' | 'datetime';
type Column = { key: string; label: string; type: ValueType };
type Row = Record<string, string | number | null>;
type Option = { value: string; label: string };

type Kpi = {
    label: string;
    value: number | string;
    type: ValueType;
    icon: string;
    change: number | null;
    hint: string | null;
};

type Chart = {
    type: 'area' | 'bar';
    title: string;
    series: { key: string; label: string; type: ValueType }[];
    data: Row[];
};

type Breakdown = { title: string; columns: Column[]; rows: Row[] };

type Filters = {
    preset: string;
    from: string;
    to: string;
    customer_type: string;
    dealer_group: string;
    warehouse: string;
    category: string;
    brand: string;
    group_by: string;
    stock_state: string;
    threshold: string;
};

type Props = {
    /** Ana para birimi. */
    currency: string;
    report: {
        key: string;
        title: string;
        description: string;
        filters: string[];
        table_title: string;
    };
    filters: Filters;
    period: string;
    options: {
        presets: Option[];
        customer_types: Option[];
        dealer_groups: Option[];
        warehouses: Option[];
        categories: Option[];
        brands: Option[];
        group_by: Option[];
        stock_states: Option[];
        /** Stok Ayarları'ndaki genel kritik stok eşiği. */
        default_threshold: string;
    };
    summary: { kpis: Kpi[]; chart: Chart | null; breakdowns: Breakdown[] };
    columns: Column[];
    table: Paginated<Row>;
    exportUrl: string | null;
};

const icons: Record<string, LucideIcon> = {
    revenue: Banknote,
    orders: ShoppingCart,
    average: TrendingUp,
    discount: TicketPercent,
    shipping: Truck,
    receipt: Receipt,
    package: Package,
    packageX: PackageX,
    boxes: Boxes,
    users: Users,
    userPlus: UserPlus,
    repeat: Repeat,
    percent: Percent,
    alert: TriangleAlert,
    clock: Clock,
    fileText: FileText,
    check: Check,
    tag: Tag,
    undo: Undo2,
};

const tones: KpiTone[] = ['primary', 'sky', 'green', 'amber', 'red', 'primary'];

const numberFormat = new Intl.NumberFormat('tr-TR', {
    maximumFractionDigits: 1,
});
const compactFormat = new Intl.NumberFormat('tr-TR', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function formatValue(
    value: string | number | null | undefined,
    type: ValueType,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    switch (type) {
        case 'money':
            return formatMoney(value);
        case 'number':
            return numberFormat.format(Number(value));
        case 'percent':
            return `%${numberFormat.format(Number(value))}`;
        case 'date':
            return formatDate(String(value));
        case 'datetime':
            return formatDateTime(String(value));
        default:
            return String(value);
    }
}

const isNumeric = (type: ValueType) =>
    type === 'number' || type === 'money' || type === 'percent';

/** Günlük grafik etiketlerini eksende kısaltır (03.11.2026 → 03.11). */
const shortLabel = (value: string) =>
    /^\d{2}\.\d{2}\.\d{4}$/.test(value) ? value.slice(0, 5) : value;

function DataTable({ columns, rows }: { columns: Column[]; rows: Row[] }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {columns.map((column) => (
                        <TableHead
                            key={column.key}
                            className={cn(
                                isNumeric(column.type) && 'text-right',
                            )}
                        >
                            {column.label}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, i) => (
                    <TableRow key={i}>
                        {columns.map((column, c) => (
                            <TableCell
                                key={column.key}
                                className={cn(
                                    isNumeric(column.type) &&
                                        'text-right tabular-nums',
                                    c === 0 &&
                                        'max-w-[28rem] font-medium whitespace-normal',
                                )}
                            >
                                {formatValue(row[column.key], column.type)}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function ReportChart({ chart }: { chart: Chart }) {
    // Grafik ekseni sayı ister: tutarlar (kesin metin) yalnız çizim için sayıya çevrilir.
    const data = chart.data.map((row) => ({
        ...row,
        ...Object.fromEntries(
            chart.series.map((s) => [s.key, toNumber(row[s.key])]),
        ),
    }));
    const config = Object.fromEntries(
        chart.series.map((s, i) => [
            s.key,
            { label: s.label, color: `var(--chart-${(i % 5) + 1})` },
        ]),
    ) satisfies ChartConfig;
    const valueType = chart.series[0]?.type ?? 'number';
    const tick = (v: number) =>
        valueType === 'money'
            ? compactFormat.format(v)
            : numberFormat.format(v);

    const tooltip = (
        <ChartTooltip
            cursor={false}
            content={
                <ChartTooltipContent
                    indicator="line"
                    formatter={(value, name) => (
                        <div className="flex w-full items-center justify-between gap-4">
                            <span className="text-muted-foreground">
                                {config[name as string]?.label ?? name}
                            </span>
                            <span className="font-mono font-medium tabular-nums">
                                {formatValue(Number(value), valueType)}
                            </span>
                        </div>
                    )}
                />
            }
        />
    );

    const axes = (
        <>
            <CartesianGrid vertical={false} />
            <XAxis
                dataKey="label"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={24}
                tickFormatter={shortLabel}
            />
            <YAxis
                tickLine={false}
                axisLine={false}
                width={52}
                tickFormatter={tick}
                allowDecimals={valueType !== 'number'}
            />
        </>
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>{chart.title}</CardTitle>
            </CardHeader>
            <CardContent className="px-2 sm:px-6">
                <ChartContainer
                    config={config}
                    className="aspect-auto h-64 w-full sm:h-72"
                >
                    {chart.type === 'bar' ? (
                        <BarChart data={data}>
                            {axes}
                            {tooltip}
                            {chart.series.length > 1 && (
                                <ChartLegend content={<ChartLegendContent />} />
                            )}
                            {chart.series.map((s) => (
                                <Bar
                                    key={s.key}
                                    dataKey={s.key}
                                    fill={`var(--color-${s.key})`}
                                    radius={[4, 4, 0, 0]}
                                />
                            ))}
                        </BarChart>
                    ) : (
                        <AreaChart data={data} margin={{ left: 4, right: 8 }}>
                            <defs>
                                {chart.series.map((s) => (
                                    <linearGradient
                                        key={s.key}
                                        id={`fill-${s.key}`}
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="5%"
                                            stopColor={`var(--color-${s.key})`}
                                            stopOpacity={0.35}
                                        />
                                        <stop
                                            offset="95%"
                                            stopColor={`var(--color-${s.key})`}
                                            stopOpacity={0.02}
                                        />
                                    </linearGradient>
                                ))}
                            </defs>
                            {axes}
                            {tooltip}
                            {chart.series.map((s) => (
                                <Area
                                    key={s.key}
                                    dataKey={s.key}
                                    type="monotone"
                                    fill={`url(#fill-${s.key})`}
                                    stroke={`var(--color-${s.key})`}
                                    strokeWidth={2}
                                />
                            ))}
                        </AreaChart>
                    )}
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function BreakdownCard({ breakdown }: { breakdown: Breakdown }) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <CardHeader className="border-b py-3">
                <CardTitle className="text-sm">{breakdown.title}</CardTitle>
            </CardHeader>
            <div className="[&_td:first-child]:pl-4 [&_td:last-child]:pr-4 [&_th]:text-xs [&_th:first-child]:pl-4 [&_th:last-child]:pr-4">
                {breakdown.rows.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                        {t('Bu dönemde kayıt yok.')}
                    </p>
                ) : (
                    <DataTable
                        columns={breakdown.columns}
                        rows={breakdown.rows}
                    />
                )}
            </div>
        </Card>
    );
}

export default function ReportShow({
    currency,
    report,
    filters,
    period,
    options,
    summary,
    columns,
    table,
    exportUrl,
}: Props) {
    setBaseCurrency(currency);
    const uses = (name: string) => report.filters.includes(name);
    const [threshold, setThreshold] = useState(filters.threshold);

    // Boş değerler sorgu dizesine gitmez; filtre değişince ilk sayfaya dönülür.
    const apply = (patch: Partial<Filters>) => {
        const next: Partial<Filters> = { ...filters, ...patch };

        if (next.preset !== 'custom') {
            delete next.from;
            delete next.to;
        }

        if (next.threshold === options.default_threshold) {
            delete next.threshold;
        }

        router.get(
            window.location.pathname,
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reset = () =>
        router.get(window.location.pathname, {}, { preserveScroll: true });

    const hasFilter = Boolean(
        filters.preset !== '30d' ||
        filters.customer_type ||
        filters.dealer_group ||
        filters.warehouse ||
        filters.category ||
        filters.brand ||
        filters.group_by ||
        filters.stock_state,
    );

    // Müşteri tipi + bayi grubu tek seçimde: "group:{id}" bayi grubunu seçer.
    const customerOptions: Option[] = [
        ...options.customer_types.filter((o) => o.value !== 'dealer'),
        ...options.customer_types
            .filter((o) => o.value === 'dealer')
            .map((o) => ({ ...o, label: t('Tüm bayiler') })),
        ...options.dealer_groups.map((g) => ({
            value: `group:${g.value}`,
            label: t('Bayi: :label', { label: g.label }),
        })),
    ];
    const customerValue = filters.dealer_group
        ? `group:${filters.dealer_group}`
        : filters.customer_type;
    const applyCustomer = (value: string) =>
        value.startsWith('group:')
            ? apply({ customer_type: '', dealer_group: value.slice(6) })
            : apply({ customer_type: value, dealer_group: '' });

    const custom = filters.preset === 'custom';
    const applyPreset = (value: string) =>
        value === 'custom'
            ? apply({ preset: 'custom', from: filters.from, to: filters.to })
            : apply({ preset: value || '30d' });

    return (
        <>
            <Head title={report.title} />

            <Page embedded>
                {/* Rapor adı sekmede; burada yalnız filtreler, dönem notu ve Excel. */}
                <Card className="gap-0 py-0">
                    <CardContent className="flex flex-wrap items-center gap-2 p-3">
                        <SearchSelect
                            aria-label={t('Dönem')}
                            className="w-full sm:w-44"
                            value={filters.preset}
                            onChange={applyPreset}
                            options={options.presets}
                        />
                        {custom && (
                            <>
                                <Input
                                    type="date"
                                    aria-label={t('Başlangıç tarihi')}
                                    title={t('Başlangıç tarihi')}
                                    className="w-full sm:w-40"
                                    value={filters.from}
                                    onChange={(e) =>
                                        e.target.value &&
                                        apply({ from: e.target.value })
                                    }
                                />
                                <Input
                                    type="date"
                                    aria-label={t('Bitiş tarihi')}
                                    title={t('Bitiş tarihi')}
                                    className="w-full sm:w-40"
                                    value={filters.to}
                                    onChange={(e) =>
                                        e.target.value &&
                                        apply({ to: e.target.value })
                                    }
                                />
                            </>
                        )}
                        {options.group_by.length > 0 && (
                            <SearchSelect
                                aria-label={t('Gruplama')}
                                className="w-full sm:w-40"
                                value={filters.group_by || 'product'}
                                onChange={(v) => apply({ group_by: v })}
                                options={options.group_by.map((o) => ({
                                    ...o,
                                    label: t(':o_label bazında', {
                                        o_label: o.label,
                                    }),
                                }))}
                            />
                        )}
                        {uses('customer') && (
                            <SearchSelect
                                aria-label={t('Müşteri')}
                                className="w-full sm:w-52"
                                value={customerValue}
                                onChange={applyCustomer}
                                options={customerOptions}
                                placeholder={t('Müşteri: tümü')}
                                clearable
                            />
                        )}
                        {uses('warehouse') && (
                            <SearchSelect
                                aria-label={t('Depo')}
                                className="w-full sm:w-44"
                                value={filters.warehouse}
                                onChange={(v) => apply({ warehouse: v })}
                                options={options.warehouses}
                                placeholder={t('Depo: tümü')}
                                clearable
                            />
                        )}
                        {uses('category') && (
                            <SearchSelect
                                aria-label={t('Kategori')}
                                className="w-full sm:w-48"
                                value={filters.category}
                                onChange={(v) => apply({ category: v })}
                                options={options.categories}
                                placeholder={t('Kategori: tümü')}
                                clearable
                            />
                        )}
                        {uses('brand') && (
                            <SearchSelect
                                aria-label={t('Marka')}
                                className="w-full sm:w-40"
                                value={filters.brand}
                                onChange={(v) => apply({ brand: v })}
                                options={options.brands}
                                placeholder={t('Marka: tümü')}
                                clearable
                            />
                        )}
                        {uses('stock_state') && (
                            <SearchSelect
                                aria-label={t('Stok durumu')}
                                className="w-full sm:w-48"
                                value={filters.stock_state}
                                onChange={(v) => apply({ stock_state: v })}
                                options={options.stock_states}
                                placeholder={t('Stok durumu: tümü')}
                                clearable
                            />
                        )}
                        {filters.stock_state === 'low' && (
                            <form
                                className="flex items-center gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    apply({ threshold });
                                }}
                            >
                                <label
                                    htmlFor="report-threshold"
                                    className="text-sm whitespace-nowrap text-muted-foreground"
                                >
                                    {t('Eşik ≤')}
                                </label>
                                <Input
                                    id="report-threshold"
                                    type="number"
                                    min={0}
                                    className="w-20"
                                    value={threshold}
                                    onChange={(e) =>
                                        setThreshold(e.target.value)
                                    }
                                    onBlur={() =>
                                        threshold !== filters.threshold &&
                                        apply({ threshold })
                                    }
                                />
                            </form>
                        )}
                        <div className="ml-auto flex items-center gap-1">
                            {hasFilter && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={reset}
                                            aria-label={t('Filtreleri temizle')}
                                        >
                                            <RotateCcw />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {t('Filtreleri temizle')}
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            {exportUrl && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="icon"
                                        >
                                            <a
                                                href={exportUrl}
                                                aria-label={t("Excel'e aktar")}
                                            >
                                                <FileSpreadsheet />
                                            </a>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {t("Excel'e aktar")}
                                    </TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                    </CardContent>
                    <p className="border-t px-3 py-2 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {period}
                        </span>
                        {' · '}
                        {report.description}
                    </p>
                </Card>

                {summary.kpis.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                        {summary.kpis.map((kpi, i) => (
                            <KpiTile
                                key={kpi.label}
                                icon={icons[kpi.icon] ?? TrendingUp}
                                label={kpi.label}
                                value={formatValue(kpi.value, kpi.type)}
                                hint={kpi.hint ?? undefined}
                                delta={kpi.change}
                                tone={tones[i % tones.length]}
                            />
                        ))}
                    </div>
                )}

                {summary.chart && <ReportChart chart={summary.chart} />}

                {summary.breakdowns.length > 0 && (
                    <div className="grid items-start gap-4 lg:grid-cols-2">
                        {summary.breakdowns.map((breakdown) => (
                            <BreakdownCard
                                key={breakdown.title}
                                breakdown={breakdown}
                            />
                        ))}
                    </div>
                )}

                <TableCard
                    toolbar={
                        <div className="flex w-full items-center justify-between gap-2">
                            <h2 className="font-medium">
                                {report.table_title}
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {t(':total satır', {
                                    total: numberFormat.format(table.total),
                                })}
                            </span>
                        </div>
                    }
                    footer={
                        table.last_page > 1 ? (
                            <DataPagination
                                paginator={table}
                                label={t('satır')}
                            />
                        ) : undefined
                    }
                >
                    {table.data.length === 0 ? (
                        <Empty className="py-16">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText />
                                </EmptyMedia>
                                <EmptyTitle>{t('Kayıt yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Seçili dönem ve filtrelerde veri bulunamadı.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable columns={columns} rows={table.data} />
                    )}
                </TableCard>
            </Page>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: t('Raporlar'), href: index() }],
};
