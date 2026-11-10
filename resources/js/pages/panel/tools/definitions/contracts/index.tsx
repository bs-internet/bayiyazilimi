import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil, Plus } from 'lucide-react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { StatusSwitch } from '@/components/shop/form-fields';
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
import {
    create,
    edit,
    index,
    status as statusRoute,
} from '@/routes/panel/shop/contracts';
import type { Contract } from '@/types';

type Props = {
    contracts: Contract[];
    /** Üyelik formu var mı (site herkese açık, vitrin)? Yoksa işaret yok sayılır. */
    registration: boolean;
};

/** Sözleşmenin gösterildiği yerler: ödeme formu, üyelik formu ya da yalnız bayi paneli. */
function Placements({
    contract,
    registration,
}: {
    contract: Contract;
    registration: boolean;
}) {
    const onRegistration = registration && contract.show_on_registration;

    if (!contract.show_on_checkout && !onRegistration) {
        return <Badge variant="outline">{t('Genel')}</Badge>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {contract.show_on_checkout ? (
                <Badge variant="secondary">{t('Ödeme formu')}</Badge>
            ) : null}
            {onRegistration ? (
                <Badge variant="secondary">{t('Üyelik formu')}</Badge>
            ) : null}
        </div>
    );
}

export default function ContractIndex({ contracts, registration }: Props) {
    const activeCount = contracts.filter((c) => c.status === 'active').length;

    return (
        <>
            <Head title={t('Sözleşmeler')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Sözleşmeler')}
                    meta={t(':count sözleşme · :active aktif', {
                        count: contracts.length,
                        active: activeCount,
                    })}
                    description={t(
                        'Ödeme formunda onaylatılan metinler. Aktif sözleşmelerin hepsi bayi panelindeki Sözleşmeler bölümünde de okunur.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" />
                                {t('Yeni sözleşme')}
                            </Link>
                        </Button>
                    }
                />

                <TableCard>
                    {contracts.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText />
                                </EmptyMedia>
                                <EmptyTitle>{t('Sözleşme yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'İlk sözleşmeyi "Yeni sözleşme" ile ekleyin.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">
                                        {t('Sıra')}
                                    </TableHead>
                                    <TableHead>{t('Başlık')}</TableHead>
                                    <TableHead>
                                        {t('Gösterildiği yer')}
                                    </TableHead>
                                    <TableHead>{t('Güncelleme')}</TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {contracts.map((contract) => (
                                    <TableRow key={contract.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {contract.sort_order}
                                        </TableCell>
                                        <TableCell className="min-w-60">
                                            <Link
                                                href={edit(contract.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {contract.title}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Placements
                                                contract={contract}
                                                registration={registration}
                                            />
                                        </TableCell>
                                        <TableCell className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {contract.updated_at
                                                ? formatDateTime(
                                                      contract.updated_at,
                                                  )
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={contract.status}
                                                url={statusRoute.url(
                                                    contract.id,
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                asChild
                                            >
                                                <Link
                                                    href={edit(contract.id)}
                                                    aria-label={t(
                                                        ':title düzenle',
                                                        {
                                                            title: contract.title,
                                                        },
                                                    )}
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

ContractIndex.layout = {
    breadcrumbs: [{ title: t('Sözleşmeler'), href: index() }],
};
