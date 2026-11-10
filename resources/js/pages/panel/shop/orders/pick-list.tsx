import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, PackageSearch, Printer } from 'lucide-react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import { dashboard } from '@/routes/panel';
import { index, pickList, show } from '@/routes/panel/shop/orders';

type OrderRef = { number: string; quantity: number };

type PickLine = {
    location: string;
    unassigned: boolean;
    sku: string | null;
    barcode: string | null;
    name: string | null;
    unit: string | null;
    lot: string | null;
    expires_at: string | null;
    quantity: number;
    short: boolean;
    orders: OrderRef[];
};

type Props = {
    selected: boolean;
    truncated: boolean;
    maxOrders: number;
    orders: {
        id: string;
        number: string;
        customer: string;
        company: string | null;
        city: string | null;
        status_label: string;
        items: number;
        created_at: string;
    }[];
    list: {
        warehouses: {
            id: string | null;
            name: string;
            quantity: number;
            lines: PickLine[];
        }[];
        unlocated: {
            sku: string;
            name: string;
            quantity: number;
            orders: OrderRef[];
        }[];
        totals: { lines: number; quantity: number };
    };
    generatedAt: string;
};

const formatDay = (iso: string) => iso.split('-').reverse().join('.');

/** Yazdırılan listede elle işaretlemek için boş kutu. */
function Tick() {
    return (
        <span
            aria-hidden
            className="inline-block size-4 rounded-sm border-2 border-foreground/60 align-middle"
        />
    );
}

function Orders({ orders }: { orders: OrderRef[] }) {
    return (
        <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
            {orders.map((o) => (
                <span key={o.number} className="font-mono whitespace-nowrap">
                    #{o.number}
                    <span className="text-muted-foreground">
                        {' '}
                        × {o.quantity}
                    </span>
                </span>
            ))}
        </div>
    );
}

export default function OrderPickList({
    selected,
    truncated,
    maxOrders,
    orders,
    list,
    generatedAt,
}: Props) {
    const empty = list.totals.lines === 0;

    return (
        <>
            <Head title={t('Toplama Listesi')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Satış') },
                        { title: t('Siparişler'), href: index() },
                        { title: t('Toplama Listesi') },
                    ]}
                    title={t('Toplama Listesi')}
                    meta={t(
                        ':orders_length sipariş · :lines satır · :quantity adet · :generated_at',
                        {
                            orders_length: orders.length,
                            lines: list.totals.lines,
                            quantity: list.totals.quantity,
                            generated_at: formatDateTime(generatedAt),
                        },
                    )}
                    description={
                        selected
                            ? t(
                                  'Seçilen siparişlerin ürünleri, depo içinde toplama sırasına (koridor / raf / göz) göre.',
                              )
                            : t(
                                  '"Hazırlanıyor" durumundaki tüm siparişler. Belirli siparişler için Siparişler listesinden seçip "Toplama listesi"ne basın.',
                              )
                    }
                    actions={
                        <div className="flex gap-2 print:hidden">
                            <Button variant="outline" asChild>
                                <Link href={index()}>
                                    <ArrowLeft data-icon="inline-start" />
                                    {t('Siparişler')}
                                </Link>
                            </Button>
                            {selected && (
                                <Button variant="outline" asChild>
                                    <Link href={pickList()}>
                                        <PackageSearch data-icon="inline-start" />
                                        {t('Tüm hazırlananlar')}
                                    </Link>
                                </Button>
                            )}
                            <Button
                                onClick={() => window.print()}
                                disabled={empty}
                            >
                                <Printer data-icon="inline-start" />
                                {t('Yazdır')}
                            </Button>
                        </div>
                    }
                />

                {truncated && (
                    <p className="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="size-4" />
                        {t('Liste en eski :count siparişle sınırlandı.', {
                            count: maxOrders,
                        })}
                    </p>
                )}

                {empty ? (
                    <SectionCard>
                        <Empty className="py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <PackageSearch />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {t('Toplanacak ürün yok')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {selected
                                        ? t(
                                              'Seçilen siparişlerde toplanacak kalem bulunmadı.',
                                          )
                                        : t(
                                              '"Hazırlanıyor" durumunda sipariş yok.',
                                          )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </SectionCard>
                ) : (
                    <>
                        {list.warehouses.map((w) => (
                            <SectionCard
                                key={w.id ?? w.name}
                                flush
                                title={w.name}
                                description={t(
                                    ':lines_length satır · :quantity adet',
                                    {
                                        lines_length: w.lines.length,
                                        quantity: w.quantity,
                                    },
                                )}
                            >
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-8" />
                                            <TableHead>
                                                {t('Lokasyon')}
                                            </TableHead>
                                            <TableHead>{t('Ürün')}</TableHead>
                                            <TableHead>
                                                {t('Lot / Seri')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Adet')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Siparişler')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {w.lines.map((l, i) => (
                                            <TableRow
                                                key={i}
                                                className="break-inside-avoid"
                                            >
                                                <TableCell>
                                                    <Tick />
                                                </TableCell>
                                                <TableCell
                                                    className={cn(
                                                        'font-mono font-semibold whitespace-nowrap',
                                                        l.unassigned &&
                                                            'font-sans font-normal text-muted-foreground',
                                                    )}
                                                >
                                                    {l.location}
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    <div className="font-medium">
                                                        {l.name}
                                                    </div>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {l.sku}
                                                        {l.barcode
                                                            ? ` · ${l.barcode}`
                                                            : ''}
                                                    </div>
                                                    {l.short && (
                                                        <Badge
                                                            variant="outline"
                                                            className="mt-1 text-amber-700 dark:text-amber-400"
                                                        >
                                                            {t(
                                                                'Stok yetmeden düşüldü',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {l.lot ? (
                                                        <>
                                                            <span className="font-mono">
                                                                {l.lot}
                                                            </span>
                                                            {l.expires_at && (
                                                                <div className="text-xs text-muted-foreground">
                                                                    {t(
                                                                        'SKT :expires_at',
                                                                        {
                                                                            expires_at:
                                                                                formatDay(
                                                                                    l.expires_at,
                                                                                ),
                                                                        },
                                                                    )}
                                                                </div>
                                                            )}
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-base font-semibold tabular-nums">
                                                    {l.quantity}
                                                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                        {l.unit}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    <Orders orders={l.orders} />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </SectionCard>
                        ))}

                        {list.unlocated.length > 0 && (
                            <SectionCard
                                flush
                                title={t('Lokasyonsuz')}
                                description={t(
                                    'Stoktan düşülmeyen kalemler (stok takipsiz ürün vb.).',
                                )}
                            >
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-8" />
                                            <TableHead>{t('Ürün')}</TableHead>
                                            <TableHead className="text-right">
                                                {t('Adet')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Siparişler')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {list.unlocated.map((u) => (
                                            <TableRow key={u.sku}>
                                                <TableCell>
                                                    <Tick />
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    <div className="font-medium">
                                                        {u.name}
                                                    </div>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {u.sku}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right text-base font-semibold tabular-nums">
                                                    {u.quantity}
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    <Orders orders={u.orders} />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </SectionCard>
                        )}

                        <SectionCard
                            flush
                            title={t('Siparişler')}
                            className="break-before-page"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8" />
                                        <TableHead>{t('Sipariş')}</TableHead>
                                        <TableHead>{t('Müşteri')}</TableHead>
                                        <TableHead>{t('Şehir')}</TableHead>
                                        <TableHead>{t('Durum')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Adet')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.map((o) => (
                                        <TableRow key={o.id}>
                                            <TableCell>
                                                <Tick />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                <Link
                                                    href={show(o.id)}
                                                    className="font-mono font-medium hover:underline"
                                                >
                                                    #{o.number}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        o.created_at,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                <div>{o.customer}</div>
                                                {o.company && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {o.company}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {o.city ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {o.status_label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {o.items}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </SectionCard>
                    </>
                )}
            </Page>
        </>
    );
}

OrderPickList.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Toplama Listesi'), href: pickList() },
    ],
};
