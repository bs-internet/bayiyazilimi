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
    forceDelete as cityForceDelete,
    index as cityIndex,
    restore as cityRestore,
} from '@/routes/panel/tools/definitions/countries/cities';
import type { City, Country, Paginated } from '@/types';

type Props = {
    cities: Paginated<City>;
    country: { data: Country };
};

export default function CityDeleted({ cities, country }: Props) {
    const parent = country.data;

    return (
        <>
            <Head
                title={t(':parent_name silinen şehirler', {
                    parent_name: parent.name,
                })}
            />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t(':parent_name silinen şehirler', {
                            parent_name: parent.name,
                        })}
                        description={t(
                            'Silinen şehir kayıtlarını geri alın veya kalıcı silin.',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={cityIndex(parent).url}>
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
                            {cities.data.map((city) => {
                                const routeArgs = {
                                    country: parent,
                                    city: city.id,
                                };

                                return (
                                    <TableRow key={city.id}>
                                        <TableCell className="font-medium">
                                            {city.code}
                                        </TableCell>
                                        <TableCell>{city.name}</TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Form
                                                    {...cityRestore.form(
                                                        routeArgs,
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
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <RotateCcw data-icon="inline-start" />
                                                            {t('Geri al')}
                                                        </Button>
                                                    )}
                                                </Form>
                                                <ConfirmDelete
                                                    action={cityForceDelete(
                                                        routeArgs,
                                                    )}
                                                    title={t(
                                                        ':city_name kalıcı silinsin mi?',
                                                        {
                                                            city_name:
                                                                city.name,
                                                        },
                                                    )}
                                                    description={t(
                                                        'Bu işlem geri alınamaz; kayıt veritabanından tamamen kaldırılır.',
                                                    )}
                                                    confirmLabel={t(
                                                        'Kalıcı sil',
                                                    )}
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
                                );
                            })}

                            {cities.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t('Silinen şehir kaydı bulunamadı.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={cities} showRange={false} />
            </div>
        </>
    );
}
