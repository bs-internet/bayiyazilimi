import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import PermissionsController from '@/actions/App/Http/Controllers/Panel/Settings/Permissions/PermissionsController';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import { index as permissionsIndex } from '@/routes/panel/settings/permissions';
import type { Permission } from '@/types';

type Props = {
    groups: Record<string, Permission[]>;
};

export default function PermissionsDeleted({ groups }: Props) {
    const [forcing, setForcing] = useState<Permission | null>(null);

    return (
        <>
            <Head title={t('Silinen Yetkiler')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                    <Heading
                        variant="small"
                        title={t('Silinen Yetkiler')}
                        description={t(
                            'Soft-delete edilmiş izinler. Geri yüklenebilir veya kalıcı olarak silinebilir.',
                        )}
                    />
                    <Button asChild variant="outline">
                        <Link href={permissionsIndex().url}>
                            <ArrowLeft data-icon="inline-start" />
                            {t('Listeye dön')}
                        </Link>
                    </Button>
                </div>

                {Object.keys(groups).length === 0 ? (
                    <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {t('Silinmiş izin yok.')}
                    </div>
                ) : (
                    Object.entries(groups).map(([group, items]) => (
                        <section key={group} className="flex flex-col gap-2">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                {group}
                            </h2>
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Etiket')}</TableHead>
                                            <TableHead className="font-mono">
                                                {t('İzin Adı')}
                                            </TableHead>
                                            <TableHead className="w-24">
                                                {t('Görevler')}
                                            </TableHead>
                                            <TableHead className="w-40 text-right">
                                                {t('İşlemler')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.uuid}>
                                                <TableCell>
                                                    {item.label ?? (
                                                        <span className="text-muted-foreground italic">
                                                            {t('(etiket yok)')}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary">
                                                        {item.roles_count}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => {
                                                                router.patch(
                                                                    PermissionsController.restore(
                                                                        {
                                                                            permission:
                                                                                item.uuid,
                                                                        },
                                                                    ).url,
                                                                    undefined,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                            aria-label={t(
                                                                'Geri Yükle',
                                                            )}
                                                        >
                                                            <RotateCcw />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setForcing(item)
                                                            }
                                                            aria-label={t(
                                                                'Kalıcı Sil',
                                                            )}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </section>
                    ))
                )}
            </div>

            <AlertDialog
                open={forcing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForcing(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {t('İzni kalıcı sil')}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            <span className="font-mono">{forcing?.name}</span>{' '}
                            {t('izni kalıcı olarak silinecek')}
                            {forcing && forcing.roles_count > 0
                                ? t(
                                      ' ve :roles_count rolden tamamen kaldırılacak',
                                      { roles_count: forcing.roles_count },
                                  )
                                : ''}
                            {t('. Bu işlem geri alınamaz.')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>
                            <X />
                            {t('İptal')}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                if (!forcing) {
                                    return;
                                }

                                router.delete(
                                    PermissionsController.forceDelete({
                                        permission: forcing.uuid,
                                    }).url,
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setForcing(null),
                                    },
                                );
                            }}
                        >
                            <Trash2 />
                            {t('Kalıcı Sil')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

PermissionsDeleted.layout = {
    breadcrumbs: [{ title: t('Silinen Yetkiler'), href: dashboard() }],
};
