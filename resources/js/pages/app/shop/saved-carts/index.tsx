import { Head, Link } from '@inertiajs/react';
import {
    Archive,
    Eye,
    MoreHorizontal,
    Pencil,
    ShoppingBag,
    ShoppingBasket,
} from 'lucide-react';
import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DataPagination } from '@/components/data-pagination';
import { SavedCartDialog } from '@/components/dealer-shop/saved-cart-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
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
import { dashboard } from '@/routes/app';
import cart from '@/routes/app/shop/cart';
import savedCarts from '@/routes/app/shop/saved-carts';
import type { Paginated } from '@/types';

type SavedCartRow = {
    id: string;
    name: string;
    note: string | null;
    item_count: number;
    estimated_total: string;
    estimated_total_formatted: string;
    created_by: string | null;
    created_at: string | null;
};

type Props = {
    savedCarts: Paginated<SavedCartRow>;
    cartCount: number;
};

/** Bayi paneli: Kayıtlı Sepetler listesi. */
export default function SavedCartsIndex({
    savedCarts: paginator,
    cartCount,
}: Props) {
    const rows = paginator.data;

    return (
        <>
            <Head title={t('Kayıtlı sepetler')} />
            <Page>
                <PageTitle
                    title={t('Kayıtlı sepetler')}
                    trail={[
                        { title: t('Panel'), href: dashboard() },
                        { title: t('Kayıtlı sepetler') },
                    ]}
                    description={t(
                        'Sık verdiğiniz siparişleri sepet olarak saklayın; tek tıkla sepete yükleyin ya da doğrudan siparişe dönüştürün. Tutarlar güncel fiyatlarla tahminidir.',
                    )}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={cart.index()}>
                                <ShoppingBasket data-icon="inline-start" />
                                {t('Sipariş ver')}
                                {cartCount > 0 ? ` (${cartCount})` : ''}
                            </Link>
                        </Button>
                    }
                />

                {rows.length === 0 ? (
                    <Empty className="border bg-card">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Archive />
                            </EmptyMedia>
                            <EmptyTitle>
                                {t('Henüz kayıtlı sepetiniz yok')}
                            </EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Sipariş ver sayfasındaki “Sepeti kaydet” ile sepetinizi bir adla saklayabilirsiniz.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild>
                                <Link href={cart.index()}>
                                    <ShoppingBasket data-icon="inline-start" />
                                    {t('Sipariş ver')}
                                </Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <TableCard
                        footer={
                            <DataPagination
                                paginator={paginator}
                                label={t('sepet')}
                            />
                        }
                    >
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Sepet')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Ürün sayısı')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Tahmini tutar')}
                                        </TableHead>
                                        <TableHead>{t('Oluşturma')}</TableHead>
                                        <TableHead className="w-12">
                                            <span className="sr-only">
                                                {t('İşlemler')}
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link
                                                    href={savedCarts.show(
                                                        row.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                {row.note && (
                                                    <div className="line-clamp-1 max-w-80 text-xs text-muted-foreground">
                                                        {row.note}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.item_count}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {row.estimated_total_formatted}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatDateTime(row.created_at)}
                                                {row.created_by && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {row.created_by}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <RowActions row={row} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </TableCard>
                )}
            </Page>
        </>
    );
}

function RowActions({ row }: { row: SavedCartRow }) {
    return (
        <div className="flex items-center justify-end gap-1">
            <Button variant="outline" size="sm" asChild>
                <Link href={savedCarts.checkout(row.id)}>
                    <ShoppingBag data-icon="inline-start" />
                    {t('Sipariş ver')}
                </Link>
            </Button>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('İşlemler')}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link href={savedCarts.show(row.id)}>
                            <Eye /> {t('Detay')}
                        </Link>
                    </DropdownMenuItem>
                    <SavedCartDialog
                        savedCart={row}
                        trigger={
                            <DropdownMenuItem
                                onSelect={(e) => e.preventDefault()}
                            >
                                <Pencil /> {t('Düzenle')}
                            </DropdownMenuItem>
                        }
                    />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

SavedCartsIndex.layout = {
    breadcrumbs: [{ title: t('Kayıtlı sepetler'), href: savedCarts.index() }],
};
