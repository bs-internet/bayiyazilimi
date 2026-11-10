import { Form, Head } from '@inertiajs/react';
import { Factory, Pencil, Plus, Save } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import {
    StatusSwitch,
    TextareaField,
    TextField,
} from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { FieldGroup } from '@/components/ui/field';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
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
    destroy,
    index,
    status as statusRoute,
    store,
    update,
} from '@/routes/panel/shop/suppliers';
import type { Status } from '@/types';

type SupplierRow = {
    id: string;
    name: string;
    tax_office: string | null;
    tax_number: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    note: string | null;
    status: Status;
    receipts_count: number;
};

type Props = {
    suppliers: SupplierRow[];
};

function SupplierSheet({
    open,
    onOpenChange,
    supplier,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    supplier: SupplierRow | null;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {supplier ? `${supplier.name}` : t('Tedarikçi ekle')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            'Mal kabulde (stok girişi) tedarikçi bu listeden seçilir.',
                        )}
                    </SheetDescription>
                </SheetHeader>
                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
                    <Form
                        key={supplier?.id ?? 'new'}
                        {...(supplier
                            ? update.form(supplier.id)
                            : store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FieldGroup>
                                    <TextField
                                        name="name"
                                        label={t('Tedarikçi adı')}
                                        defaultValue={supplier?.name}
                                        error={errors.name}
                                        required
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <TextField
                                            name="tax_office"
                                            label={t('Vergi dairesi')}
                                            defaultValue={supplier?.tax_office}
                                            error={errors.tax_office}
                                        />
                                        <TextField
                                            name="tax_number"
                                            label={t('Vergi / TC no')}
                                            defaultValue={supplier?.tax_number}
                                            error={errors.tax_number}
                                        />
                                        <TextField
                                            name="phone"
                                            label={t('Telefon')}
                                            defaultValue={supplier?.phone}
                                            error={errors.phone}
                                        />
                                        <TextField
                                            name="email"
                                            label={t('E-posta')}
                                            type="email"
                                            defaultValue={supplier?.email}
                                            error={errors.email}
                                        />
                                    </div>
                                    <TextareaField
                                        name="address"
                                        label={t('Adres')}
                                        defaultValue={supplier?.address}
                                        error={errors.address}
                                    />
                                    <TextField
                                        name="note"
                                        label={t('Not')}
                                        defaultValue={supplier?.note}
                                        error={errors.note}
                                    />
                                </FieldGroup>
                                {supplier && supplier.receipts_count > 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            ':receipts_count mal kabulde kullanıldığı için silinemez; pasifleştirebilirsiniz.',
                                            {
                                                receipts_count:
                                                    supplier.receipts_count,
                                            },
                                        )}
                                    </p>
                                ) : null}
                                <FormActions
                                    destroy={
                                        supplier ? (
                                            <DeleteButton
                                                action={destroy(supplier.id)}
                                                title={t(
                                                    ':supplier_name silinsin mi?',
                                                    {
                                                        supplier_name:
                                                            supplier.name,
                                                    },
                                                )}
                                                description={t(
                                                    'Bu işlem geri alınamaz.',
                                                )}
                                                disabled={
                                                    supplier.receipts_count > 0
                                                }
                                            />
                                        ) : null
                                    }
                                >
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {t('Kaydet')}
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}

export default function SupplierIndex({ suppliers }: Props) {
    const [sheet, setSheet] = useState<{
        open: boolean;
        supplier: SupplierRow | null;
    }>({ open: false, supplier: null });
    const activeCount = suppliers.filter((s) => s.status === 'active').length;

    return (
        <>
            <Head title={t('Tedarikçiler')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Tedarikçiler')}
                    meta={t(
                        ':suppliers_length tedarikçi · :active_count aktif',
                        {
                            suppliers_length: suppliers.length,
                            active_count: activeCount,
                        },
                    )}
                    description={t(
                        'Mal kabulde seçilen tedarikçiler. Mal kabulü olan tedarikçi silinemez, pasifleştirilir.',
                    )}
                    actions={
                        <Button
                            onClick={() =>
                                setSheet({ open: true, supplier: null })
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {t('Yeni tedarikçi')}
                        </Button>
                    }
                />

                <TableCard>
                    {suppliers.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Factory />
                                </EmptyMedia>
                                <EmptyTitle>{t('Tedarikçi yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'İlk tedarikçiyi "Yeni tedarikçi" ile ekleyin.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Tedarikçi')}</TableHead>
                                    <TableHead>{t('Vergi no')}</TableHead>
                                    <TableHead>{t('İletişim')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Mal kabul')}
                                    </TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {suppliers.map((supplier) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell className="font-medium">
                                            {supplier.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {supplier.tax_number ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {[supplier.phone, supplier.email]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {supplier.receipts_count}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={supplier.status}
                                                url={statusRoute.url(
                                                    supplier.id,
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':supplier_name düzenle',
                                                    {
                                                        supplier_name:
                                                            supplier.name,
                                                    },
                                                )}
                                                onClick={() =>
                                                    setSheet({
                                                        open: true,
                                                        supplier,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>

            <SupplierSheet
                open={sheet.open}
                onOpenChange={(open) => setSheet((s) => ({ ...s, open }))}
                supplier={sheet.supplier}
            />
        </>
    );
}

SupplierIndex.layout = {
    breadcrumbs: [{ title: t('Tedarikçiler'), href: index() }],
};
