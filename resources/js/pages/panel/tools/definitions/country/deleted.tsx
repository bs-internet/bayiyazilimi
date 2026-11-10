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
    forceDelete as countryForceDelete,
    index as countryIndex,
    restore as countryRestore,
} from '@/routes/panel/tools/definitions/countries';
import type { Country, Paginated } from '@/types';

type Props = {
    countries: Paginated<Country>;
};

export default function CountryDeleted({ countries }: Props) {
    return (
        <>
            <Head title={t('Silinen ülkeler')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Silinen ülkeler')}
                        description={t(
                            'Silinen ülke kayıtlarını geri alın veya kalıcı silin.',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={countryIndex().url}>
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
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {countries.data.map((country) => (
                                <TableRow key={country.id}>
                                    <TableCell className="font-medium">
                                        {country.code}
                                    </TableCell>
                                    <TableCell>{country.name}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Form
                                                {...countryRestore.form(
                                                    country.id,
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
                                                action={countryForceDelete(
                                                    country.id,
                                                )}
                                                title={t(
                                                    ':country_name kalıcı silinsin mi?',
                                                    {
                                                        country_name:
                                                            country.name,
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

                            {countries.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t('Silinen ülke kaydı bulunamadı.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={countries} showRange={false} />
            </div>
        </>
    );
}
