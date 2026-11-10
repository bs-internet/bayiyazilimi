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
    forceDelete as languageForceDelete,
    index as languageIndex,
    restore as languageRestore,
} from '@/routes/panel/tools/definitions/languages';
import type { Language, Paginated } from '@/types';

type Props = {
    languages: Paginated<Language>;
};

export default function LanguageDeleted({ languages }: Props) {
    return (
        <>
            <Head title={t('Silinen diller')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Silinen diller')}
                        description={t(
                            'Silinen dil kayıtlarını geri alın veya kalıcı silin.',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={languageIndex().url}>
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
                                <TableHead>{t('Yerel ad')}</TableHead>
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {languages.data.map((language) => (
                                <TableRow key={language.id}>
                                    <TableCell className="font-medium">
                                        {language.code}
                                    </TableCell>
                                    <TableCell>{language.name}</TableCell>
                                    <TableCell>
                                        {language.native_name}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Form
                                                {...languageRestore.form(
                                                    language.id,
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
                                                action={languageForceDelete(
                                                    language.id,
                                                )}
                                                title={t(
                                                    ':language_name kalıcı silinsin mi?',
                                                    {
                                                        language_name:
                                                            language.name,
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

                            {languages.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t('Silinen dil kaydı bulunamadı.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={languages} showRange={false} />
            </div>
        </>
    );
}
