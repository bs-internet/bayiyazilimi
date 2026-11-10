import { Form, Head, Link } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';

import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import {
    forceDelete as taxForceDelete,
    index as taxIndex,
    restore as taxRestore,
} from '@/routes/panel/tools/definitions/taxes';
import type { Paginated, Tax } from '@/types';

type Props = {
    taxes: Paginated<Tax>;
};

export default function TaxDeleted({ taxes }: Props) {
    return (
        <>
            <Head title={t('Silinen vergi oranları')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Silinen vergi oranları')}
                        description={t(
                            'Silinen vergi oranlarını geri alın veya kalıcı silin.',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={taxIndex().url}>{t('Listeye dön')}</Link>
                    </Button>
                </div>

                <div className="relative overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Ad')}</TableHead>
                                <TableHead>{t('Oran')}</TableHead>
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {taxes.data.map((tax) => (
                                <TableRow key={tax.id}>
                                    <TableCell className="font-medium">
                                        {tax.name}
                                    </TableCell>
                                    <TableCell>%{tax.rate}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Form
                                                {...taxRestore.form(tax.id)}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        <RotateCcw data-icon="inline-start" />
                                                        {t('Geri al')}
                                                    </Button>
                                                )}
                                            </Form>
                                            <ConfirmDelete
                                                action={taxForceDelete(tax.id)}
                                                title={t(
                                                    ':tax_name kalıcı silinsin mi?',
                                                    { tax_name: tax.name },
                                                )}
                                                description={t(
                                                    'Bu işlem geri alınamaz; kayıt veritabanından tamamen kaldırılır.',
                                                )}
                                                confirmLabel={t('Kalıcı sil')}
                                            >
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                >
                                                    <Trash2 data-icon="inline-start" />
                                                    {t('Kalıcı sil')}
                                                </Button>
                                            </ConfirmDelete>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {taxes.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t(
                                            'Silinen vergi oranı kaydı bulunamadı.',
                                        )}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={taxes} showRange={false} />
            </div>
        </>
    );
}
