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
    forceDelete as currencyForceDelete,
    index as currencyIndex,
    restore as currencyRestore,
} from '@/routes/panel/tools/definitions/currencies';
import type { Currency, Paginated } from '@/types';

type Props = {
    currencies: Paginated<Currency>;
};

export default function CurrencyDeleted({ currencies }: Props) {
    return (
        <>
            <Head title={t('Silinen para birimleri')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Silinen para birimleri')}
                        description={t(
                            'Silinen para birimlerini geri alın veya kalıcı silin.',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={currencyIndex().url}>
                            {t('Listeye dön')}
                        </Link>
                    </Button>
                </div>

                <div className="relative overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Kod')}</TableHead>
                                <TableHead>{t('Ad')}</TableHead>
                                <TableHead>{t('Sembol')}</TableHead>
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {currencies.data.map((currency) => (
                                <TableRow key={currency.id}>
                                    <TableCell className="font-medium">
                                        {currency.code}
                                    </TableCell>
                                    <TableCell>{currency.name}</TableCell>
                                    <TableCell>{currency.symbol}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Form
                                                {...currencyRestore.form(
                                                    currency.id,
                                                )}
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
                                                action={currencyForceDelete(
                                                    currency.id,
                                                )}
                                                title={t(
                                                    ':currency_name kalıcı silinsin mi?',
                                                    {
                                                        currency_name:
                                                            currency.name,
                                                    },
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

                            {currencies.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t(
                                            'Silinen para birimi kaydı bulunamadı.',
                                        )}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={currencies} showRange={false} />
            </div>
        </>
    );
}
