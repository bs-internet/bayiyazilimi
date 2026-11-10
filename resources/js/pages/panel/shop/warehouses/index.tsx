import { Form, Head, Link } from '@inertiajs/react';
import {
    Boxes,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    Save,
    Warehouse,
} from 'lucide-react';
import { useState } from 'react';

import { ActiveBadge } from '@/components/admin/active-badge';
import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import {
    CheckboxField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/shop/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index as stockIndex } from '@/routes/panel/shop/stock';
import { edit as stockSettingsEdit } from '@/routes/panel/shop/stock-settings';
import {
    destroy,
    index,
    show,
    store,
    update,
} from '@/routes/panel/shop/warehouses';
import type { ShopOption, ShopWarehouse } from '@/types';

type Props = {
    warehouses: ShopWarehouse[];
};

const STATUS_OPTIONS: ShopOption[] = [
    { value: 'active', label: t('Aktif') },
    { value: 'passive', label: t('Pasif') },
];

function WarehouseForm({
    warehouse,
    onDone,
}: {
    warehouse: ShopWarehouse | null;
    onDone: () => void;
}) {
    const formProps = warehouse ? update.form(warehouse.id) : store.form();

    return (
        <Form
            {...formProps}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex flex-col gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <FieldGroup>
                        <div className="grid grid-cols-2 gap-3">
                            <TextField
                                name="code"
                                label={t('Kod')}
                                required
                                defaultValue={warehouse?.code}
                                error={errors.code}
                            />
                            <TextField
                                name="sort_order"
                                label={t('Sıra')}
                                type="number"
                                min={0}
                                defaultValue={warehouse?.sort_order ?? 0}
                                error={errors.sort_order}
                            />
                        </div>
                        <TextField
                            name="name"
                            label={t('Ad')}
                            required
                            defaultValue={warehouse?.name}
                            error={errors.name}
                        />
                        <TextareaField
                            name="address"
                            label={t('Adres')}
                            rows={2}
                            defaultValue={warehouse?.address}
                            error={errors.address}
                        />
                        <TextField
                            name="phone"
                            label={t('Telefon')}
                            defaultValue={warehouse?.phone}
                            error={errors.phone}
                        />
                        <SelectField
                            name="status"
                            label={t('Durum')}
                            options={STATUS_OPTIONS}
                            required
                            defaultValue={warehouse?.status ?? 'active'}
                            error={errors.status}
                        />
                        <CheckboxField
                            name="is_sellable"
                            label={t('Satılabilir depo')}
                            description={t(
                                'Bu deponun stoğu sitede satılabilir stoğa dahil edilir ve siparişler buradan düşebilir.',
                            )}
                            defaultChecked={warehouse?.is_sellable ?? true}
                        />
                        {warehouse?.is_default ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Varsayılan satış deposu. Değiştirmek için')}{' '}
                                <Link
                                    href={stockSettingsEdit()}
                                    className="font-medium text-primary underline"
                                >
                                    {t('Stok Ayarları')}
                                </Link>
                                .
                            </p>
                        ) : null}
                    </FieldGroup>
                    <FormActions
                        destroy={
                            warehouse && (
                                <DeleteButton
                                    action={destroy(warehouse.id)}
                                    title={t(':warehouse_name silinsin mi?', {
                                        warehouse_name: warehouse.name,
                                    })}
                                    description={t(
                                        'Stoğu olan veya varsayılan depo silinemez.',
                                    )}
                                    onSuccess={onDone}
                                />
                            )
                        }
                    >
                        <Button type="submit" disabled={processing}>
                            <Save data-icon="inline-start" />
                            {t('Kaydet')}
                        </Button>
                    </FormActions>
                </>
            )}
        </Form>
    );
}

export default function WarehouseIndex({ warehouses }: Props) {
    const [editing, setEditing] = useState<ShopWarehouse | null>(null);
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const totalStock = warehouses.reduce(
        (sum, w) => sum + (w.total_quantity ?? 0),
        0,
    );

    const openSheet = (warehouse: ShopWarehouse | null) => {
        setEditing(warehouse);
        setFormKey((k) => k + 1);
        setOpen(true);
    };

    return (
        <>
            <Head title={t('Depolar')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Mağaza') }, { title: t('Depolar') }]}
                    title={t('Depolar')}
                    meta={t(
                        ':warehouses_length depo · toplam :total_stock adet stok',
                        {
                            warehouses_length: warehouses.length,
                            total_stock: totalStock,
                        },
                    )}
                    actions={
                        <Button onClick={() => openSheet(null)}>
                            <Plus data-icon="inline-start" />
                            {t('Yeni depo')}
                        </Button>
                    }
                />

                <TableCard>
                    {warehouses.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Warehouse />
                                </EmptyMedia>
                                <EmptyTitle>{t('Depo yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('İlk depoyu ekleyin.')}
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
                                    <TableHead>{t('Depo')}</TableHead>
                                    <TableHead>{t('Telefon')}</TableHead>
                                    <TableHead>{t('Durum')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Toplam stok')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {warehouses.map((warehouse) => (
                                    <TableRow key={warehouse.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {warehouse.sort_order}
                                        </TableCell>
                                        <TableCell className="min-w-60 whitespace-normal">
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                                                    <Warehouse className="size-4" />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Link
                                                            href={show(
                                                                warehouse.id,
                                                            )}
                                                            className="text-left font-medium hover:underline"
                                                        >
                                                            {warehouse.name}
                                                        </Link>
                                                        {warehouse.is_default && (
                                                            <Badge variant="secondary">
                                                                {t(
                                                                    'Varsayılan',
                                                                )}
                                                            </Badge>
                                                        )}
                                                        {!warehouse.is_sellable && (
                                                            <Badge variant="outline">
                                                                {t(
                                                                    'Satışa kapalı',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('Kod:')}{' '}
                                                        <span className="font-mono">
                                                            {warehouse.code}
                                                        </span>
                                                        {warehouse.address &&
                                                            ` · ${warehouse.address}`}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {warehouse.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge
                                                active={
                                                    warehouse.status ===
                                                    'active'
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {warehouse.total_quantity ?? 0}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={t(
                                                            'İşlemler',
                                                        )}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={show(
                                                                warehouse.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            {t('Detay')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            openSheet(warehouse)
                                                        }
                                                    >
                                                        <Pencil />
                                                        {t('Düzenle')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={stockIndex({
                                                                query: {
                                                                    warehouse:
                                                                        warehouse.id,
                                                                },
                                                            })}
                                                        >
                                                            <Boxes />
                                                            {t('Stok durumu')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            {editing ? t('Depo düzenle') : t('Depo ekle')}
                        </SheetTitle>
                        <SheetDescription>
                            {editing
                                ? editing.name
                                : t('Yeni depo tanımı oluşturun.')}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-6 px-4 pb-4">
                        <WarehouseForm
                            key={formKey}
                            warehouse={editing}
                            onDone={() => setOpen(false)}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

WarehouseIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Depolar'), href: index() },
    ],
};
