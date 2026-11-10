import { Form, Head, Link, router } from '@inertiajs/react';
import { Archive, Pencil, Plus, Save } from 'lucide-react';
import { useState } from 'react';

import { DeleteButton, FormActions } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
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
    deleted as taxDeleted,
    destroy as taxDestroy,
    status as taxStatus,
    store as taxStore,
    update as taxUpdate,
} from '@/routes/panel/tools/definitions/taxes';
import type { Paginated, Status, Tax } from '@/types';

type Props = {
    taxes: Paginated<Tax>;
    defaults: {
        default_tax_id: string | null;
    };
};

function TaxField({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    required = true,
    min,
    step,
}: {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    error?: string;
    type?: string;
    required?: boolean;
    min?: number;
    step?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                required={required}
                min={min}
                step={step}
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    );
}

function TaxStatusSwitch({ status, url }: { status: Status; url: string }) {
    const [processing, setProcessing] = useState(false);

    return (
        <Switch
            size="sm"
            checked={status === 'active'}
            disabled={processing}
            aria-label={t('Vergi oranı durumunu değiştir')}
            onCheckedChange={() => {
                setProcessing(true);

                router.patch(
                    url,
                    {},
                    {
                        preserveScroll: true,
                        onFinish: () => setProcessing(false),
                    },
                );
            }}
        />
    );
}

function TaxSaveButton({ processing }: { processing?: boolean }) {
    return (
        <Button type="submit" disabled={processing}>
            <Save data-icon="inline-start" />
            {t('Kaydet')}
        </Button>
    );
}

export default function TaxIndex({ taxes, defaults }: Props) {
    return (
        <>
            <Head title={t('Vergi Oranları')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Vergi Oranları')}
                        description={t(
                            'Sistemde kullanılacak vergi oranlarını yönetin.',
                        )}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={taxDeleted().url}>
                                <Archive data-icon="inline-start" />
                                {t('Silinenler')}
                            </Link>
                        </Button>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    {t('Yeni')}
                                </Button>
                            </SheetTrigger>
                            <SheetContent className="sm:max-w-md">
                                <SheetHeader>
                                    <SheetTitle>
                                        {t('Vergi oranı ekle')}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {t(
                                            'Yeni vergi oranı tanımı oluşturun.',
                                        )}
                                    </SheetDescription>
                                </SheetHeader>
                                <Form
                                    {...taxStore.form()}
                                    options={{ preserveScroll: true }}
                                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FieldGroup>
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="passive"
                                                />
                                                <TaxField
                                                    name="sort_order"
                                                    label={t('Sıra No')}
                                                    type="number"
                                                    min={0}
                                                    required={false}
                                                    error={errors.sort_order}
                                                />
                                                <TaxField
                                                    name="name"
                                                    label={t('Ad')}
                                                    error={errors.name}
                                                />
                                                <TaxField
                                                    name="rate"
                                                    label={t('Oran')}
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    error={errors.rate}
                                                />
                                            </FieldGroup>
                                            <div className="flex justify-end">
                                                <TaxSaveButton
                                                    processing={processing}
                                                />
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Aktif')}</TableHead>
                                <TableHead>{t('Sıra No')}</TableHead>
                                <TableHead>{t('Ad')}</TableHead>
                                <TableHead>{t('Oran')}</TableHead>
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {taxes.data.map((tax) => (
                                <TableRow key={tax.id}>
                                    <TableCell>
                                        <TaxStatusSwitch
                                            status={tax.status}
                                            url={taxStatus(tax.id).url}
                                        />
                                    </TableCell>
                                    <TableCell>{tax.sort_order}</TableCell>
                                    <TableCell className="font-medium">
                                        <div className="flex items-center gap-2">
                                            <span>{tax.name}</span>
                                            {tax.id ===
                                            defaults.default_tax_id ? (
                                                <Badge variant="secondary">
                                                    {t('Varsayılan')}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                    <TableCell>%{tax.rate}</TableCell>
                                    <TableCell className="text-right">
                                        <Sheet>
                                            <SheetTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={t('Düzenle')}
                                                >
                                                    <Pencil />
                                                    <span className="sr-only">
                                                        {t('Düzenle')}
                                                    </span>
                                                </Button>
                                            </SheetTrigger>
                                            <SheetContent className="sm:max-w-md">
                                                <SheetHeader>
                                                    <SheetTitle>
                                                        {t(
                                                            'Vergi oranı düzenle',
                                                        )}
                                                    </SheetTitle>
                                                    <SheetDescription>
                                                        {t(
                                                            ':tax_name kaydını düzenleyin.',
                                                            {
                                                                tax_name:
                                                                    tax.name,
                                                            },
                                                        )}
                                                    </SheetDescription>
                                                </SheetHeader>
                                                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
                                                    <Form
                                                        {...taxUpdate.form(
                                                            tax.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        className="flex flex-col gap-5"
                                                    >
                                                        {({
                                                            processing,
                                                            errors,
                                                        }) => (
                                                            <>
                                                                <FieldGroup>
                                                                    <TaxField
                                                                        name="sort_order"
                                                                        label={t(
                                                                            'Sıra No',
                                                                        )}
                                                                        type="number"
                                                                        min={0}
                                                                        required={
                                                                            false
                                                                        }
                                                                        defaultValue={
                                                                            tax.sort_order
                                                                        }
                                                                        error={
                                                                            errors.sort_order
                                                                        }
                                                                    />
                                                                    <TaxField
                                                                        name="name"
                                                                        label={t(
                                                                            'Ad',
                                                                        )}
                                                                        defaultValue={
                                                                            tax.name
                                                                        }
                                                                        error={
                                                                            errors.name
                                                                        }
                                                                    />
                                                                    <TaxField
                                                                        name="rate"
                                                                        label={t(
                                                                            'Oran',
                                                                        )}
                                                                        type="number"
                                                                        min={0}
                                                                        step="0.01"
                                                                        defaultValue={
                                                                            tax.rate
                                                                        }
                                                                        error={
                                                                            errors.rate
                                                                        }
                                                                    />
                                                                </FieldGroup>
                                                                <FormActions
                                                                    destroy={
                                                                        <DeleteButton
                                                                            action={taxDestroy(
                                                                                tax.id,
                                                                            )}
                                                                            title={t(
                                                                                ':tax_name vergi oranı silinsin mi?',
                                                                                {
                                                                                    tax_name:
                                                                                        tax.name,
                                                                                },
                                                                            )}
                                                                            description={t(
                                                                                'Silinen vergi oranı, silinenler sayfasından geri alınabilir.',
                                                                            )}
                                                                        />
                                                                    }
                                                                >
                                                                    <TaxSaveButton
                                                                        processing={
                                                                            processing
                                                                        }
                                                                    />
                                                                </FormActions>
                                                            </>
                                                        )}
                                                    </Form>
                                                </div>
                                            </SheetContent>
                                        </Sheet>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {taxes.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t('Vergi oranı kaydı bulunamadı.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={taxes} showRange={false} />
            </div>
        </>
    );
}

TaxIndex.layout = {
    breadcrumbs: [
        {
            title: t('Vergi Oranları'),
            href: dashboard(),
        },
    ],
};
