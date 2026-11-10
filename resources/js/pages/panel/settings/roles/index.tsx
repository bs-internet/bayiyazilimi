import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Plus, RotateCcw, Search, ShieldCheck } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import {
    create as createRole,
    index,
    show,
} from '@/routes/panel/settings/roles';
import type { Paginated, RoleItem } from '@/types';

type Props = {
    roles: Paginated<RoleItem>;
    filters: {
        q?: string;
    };
};

export default function Roles({ roles, filters }: Props) {
    return (
        <>
            <Head title={t('Görevler')} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Görevler')}
                        description={t(
                            'Sistem rollerini görüntüleyin, yeni rol oluşturun ve izin atayın.',
                        )}
                    />

                    <div className="flex w-full flex-col gap-2 sm:max-w-md sm:flex-row">
                        <Form
                            {...index.form()}
                            className="flex w-full flex-col gap-2 sm:flex-row"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    name="q"
                                    defaultValue={filters.q ?? ''}
                                    placeholder={t('Görev ara')}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Search />
                                    {t('Filtrele')}
                                </Button>
                                {(filters.q ?? '') !== '' && (
                                    <Button asChild variant="outline">
                                        <Link href={index()}>
                                            <RotateCcw />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </Form>
                        <Button asChild>
                            <Link href={createRole()}>
                                <Plus />
                                {t('Yeni Görev')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-4">
                    {roles.data.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ShieldCheck />
                                </EmptyMedia>
                                <EmptyTitle>{t('Görev bulunamadı')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('Seçili filtreye uygun rol yok.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <>
                            <div className="overflow-hidden rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Görev')}</TableHead>
                                            <TableHead>
                                                {t('İzin sayısı')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Kullanıcı sayısı')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('İşlem')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {roles.data.map((role) => (
                                            <TableRow key={role.uuid}>
                                                <TableCell>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {role.name}
                                                        </span>
                                                        {role.is_system && (
                                                            <Badge variant="secondary">
                                                                {t('Sistem')}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {role.permissions_count}
                                                </TableCell>
                                                <TableCell>
                                                    {role.users_count}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={show(
                                                                role.uuid,
                                                            )}
                                                        >
                                                            <Eye data-icon="inline-start" />
                                                            {t('Düzenle')}
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            <DataPagination paginator={roles} />
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [
        {
            title: t('Görevler'),
            href: dashboard(),
        },
    ],
};
