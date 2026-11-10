import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Ruler, Save } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { StatusSwitch, TextField } from '@/components/shop/form-fields';
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
} from '@/routes/panel/shop/units';
import type { Status } from '@/types';

type UnitRow = {
    id: string;
    name: string;
    status: Status;
    sort_order: number;
    products_count: number;
};

type Props = {
    units: UnitRow[];
};

function UnitSheet({
    open,
    onOpenChange,
    unit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unit: UnitRow | null;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {unit
                            ? t(':unit_name birimini düzenle', {
                                  unit_name: unit.name,
                              })
                            : t('Birim ekle')}
                    </SheetTitle>
                    <SheetDescription>
                        {unit
                            ? t(
                                  'Ad değişirse bu birimdeki ürünler de güncellenir.',
                              )
                            : t('Ürün eklerken birim bu listeden seçilir.')}
                    </SheetDescription>
                </SheetHeader>
                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
                    <Form
                        key={unit?.id ?? 'new'}
                        {...(unit ? update.form(unit.id) : store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FieldGroup>
                                    <TextField
                                        name="name"
                                        label={t('Birim adı')}
                                        placeholder={t(
                                            'Örn. Adet, Kutu, Litre',
                                        )}
                                        defaultValue={unit?.name}
                                        error={errors.name}
                                        required
                                    />
                                    <TextField
                                        name="sort_order"
                                        label={t('Sıra')}
                                        type="number"
                                        min={0}
                                        defaultValue={unit?.sort_order ?? 0}
                                        error={errors.sort_order}
                                    />
                                </FieldGroup>
                                {unit && unit.products_count > 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            ':products_count üründe kullanıldığı için silinemez; pasifleştirebilirsiniz.',
                                            {
                                                products_count:
                                                    unit.products_count,
                                            },
                                        )}
                                    </p>
                                ) : null}
                                <FormActions
                                    destroy={
                                        unit ? (
                                            <DeleteButton
                                                action={destroy(unit.id)}
                                                title={t(
                                                    ':unit_name birimi silinsin mi?',
                                                    { unit_name: unit.name },
                                                )}
                                                description={t(
                                                    'Bu işlem geri alınamaz.',
                                                )}
                                                disabled={
                                                    unit.products_count > 0
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

export default function UnitIndex({ units }: Props) {
    const [sheet, setSheet] = useState<{
        open: boolean;
        unit: UnitRow | null;
    }>({ open: false, unit: null });
    const activeCount = units.filter((u) => u.status === 'active').length;

    return (
        <>
            <Head title={t('Birimler')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Birimler')}
                    meta={t(':units_length birim · :active_count aktif', {
                        units_length: units.length,
                        active_count: activeCount,
                    })}
                    description={t(
                        'Ürün birimleri. Ürün eklerken birim bu listedeki aktif birimlerden seçilir.',
                    )}
                    actions={
                        <Button
                            onClick={() => setSheet({ open: true, unit: null })}
                        >
                            <Plus data-icon="inline-start" />
                            {t('Yeni birim')}
                        </Button>
                    }
                />

                <TableCard>
                    {units.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Ruler />
                                </EmptyMedia>
                                <EmptyTitle>{t('Birim yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('İlk birimi "Yeni birim" ile ekleyin.')}
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
                                    <TableHead>{t('Birim')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Ürün sayısı')}
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
                                {units.map((unit) => (
                                    <TableRow key={unit.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {unit.sort_order}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {unit.name}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {unit.products_count}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={unit.status}
                                                url={statusRoute.url(unit.id)}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':unit_name düzenle',
                                                    { unit_name: unit.name },
                                                )}
                                                onClick={() =>
                                                    setSheet({
                                                        open: true,
                                                        unit,
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

            <UnitSheet
                open={sheet.open}
                onOpenChange={(open) => setSheet((s) => ({ ...s, open }))}
                unit={sheet.unit}
            />
        </>
    );
}

UnitIndex.layout = {
    breadcrumbs: [{ title: t('Birimler'), href: index() }],
};
