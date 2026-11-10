import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { edit as productEdit } from '@/routes/panel/shop/products';
import { index } from '@/routes/panel/shop/stock-receipts';

type Props = {
    receipt: {
        id: string;
        number: string;
        supplier: {
            id: string;
            name: string;
            tax_office: string | null;
            tax_number: string | null;
            phone: string | null;
            email: string | null;
        } | null;
        document_type: string;
        document_no: string;
        document_date: string;
        warehouse: string | null;
        note: string | null;
        user: string | null;
        created_at: string;
    };
    lines: {
        id: string;
        product_id: string;
        sku: string | null;
        name: string | null;
        unit: string | null;
        location: string | null;
        lot: string | null;
        expires_at: string | null;
        quantity: number;
    }[];
};

function Info({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{children}</div>
        </div>
    );
}

export default function StockReceiptShow({ receipt, lines }: Props) {
    const total = lines.reduce((sum, l) => sum + l.quantity, 0);
    const hasLots = lines.some((l) => l.lot);

    return (
        <>
            <Head title={t('Mal Kabul :number', { number: receipt.number })} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Mal Kabul'), href: index() },
                        { title: receipt.number },
                    ]}
                    title={t('Mal Kabul :number', { number: receipt.number })}
                    meta={`${formatDateTime(receipt.created_at)} · ${receipt.user ?? t('Sistem')}`}
                    actions={
                        <div className="flex gap-2 print:hidden">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer data-icon="inline-start" />
                                {t('Yazdır')}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={index()}>
                                    <ArrowLeft data-icon="inline-start" />
                                    {t('Mal kabuller')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <SectionCard title={t('Belge')}>
                    <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Info label={t('Tedarikçi')}>
                            {receipt.supplier?.name ?? '—'}
                            {receipt.supplier?.tax_number && (
                                <div className="text-xs font-normal text-muted-foreground">
                                    {t('VN: :number', {
                                        number: receipt.supplier.tax_number,
                                    })}
                                    {receipt.supplier.tax_office &&
                                        ` · ${receipt.supplier.tax_office}`}
                                </div>
                            )}
                        </Info>
                        <Info label={t('Belge')}>
                            {receipt.document_type}{' '}
                            <span className="font-mono">
                                {receipt.document_no}
                            </span>
                        </Info>
                        <Info label={t('Belge tarihi')}>
                            {formatDate(receipt.document_date)}
                        </Info>
                        <Info label={t('Giriş deposu')}>
                            {receipt.warehouse ?? '—'}
                        </Info>
                        {receipt.note && (
                            <div className="sm:col-span-2 lg:col-span-4">
                                <Info label={t('Not')}>{receipt.note}</Info>
                            </div>
                        )}
                    </div>
                </SectionCard>

                <SectionCard
                    title={t('Satırlar (:lines_length)', {
                        lines_length: lines.length,
                    })}
                    description={t('Toplam :total adet', { total })}
                    flush
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Ürün')}</TableHead>
                                <TableHead>{t('Lokasyon')}</TableHead>
                                {hasLots && (
                                    <TableHead>{t('Lot / Seri no')}</TableHead>
                                )}
                                {hasLots && <TableHead>{t('SKT')}</TableHead>}
                                <TableHead className="text-right">
                                    {t('Miktar')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell>
                                        <Link
                                            href={productEdit(line.product_id)}
                                            className="font-medium hover:underline"
                                        >
                                            {line.name ?? '—'}
                                        </Link>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {line.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {line.location ?? '—'}
                                    </TableCell>
                                    {hasLots && (
                                        <TableCell className="font-mono text-sm">
                                            {line.lot ?? '—'}
                                        </TableCell>
                                    )}
                                    {hasLots && (
                                        <TableCell className="text-sm">
                                            {line.expires_at
                                                ? formatDate(line.expires_at)
                                                : '—'}
                                        </TableCell>
                                    )}
                                    <TableCell className="text-right tabular-nums">
                                        {line.quantity} {line.unit}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </SectionCard>
            </Page>
        </>
    );
}

StockReceiptShow.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Mal Kabul'), href: index() },
    ],
};
