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
    deleted as currencyDeleted,
    destroy as currencyDestroy,
    status as currencyStatus,
    store as currencyStore,
    update as currencyUpdate,
} from '@/routes/panel/tools/definitions/currencies';
import type { Currency, Paginated, Status } from '@/types';

type Props = {
    currencies: Paginated<Currency>;
    defaults: {
        default_currency_id: string | null;
    };
};

function CurrencyField({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    required = true,
    min,
}: {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    error?: string;
    type?: string;
    required?: boolean;
    min?: number;
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
                aria-invalid={Boolean(error)}
            />
            <InputError message={error} />
        </Field>
    );
}

const SEPARATOR_OPTIONS: { value: string; label: string }[] = [
    { value: ',', label: t('Virgül (,)') },
    { value: '.', label: t('Nokta (.)') },
    { value: ' ', label: t('Boşluk ( )') },
];

function SeparatorField({
    name,
    label,
    defaultValue,
    error,
}: {
    name: 'thousands_separator' | 'decimal_separator';
    label: string;
    defaultValue: string;
    error?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <select
                id={name}
                name={name}
                defaultValue={defaultValue}
                required
                aria-invalid={Boolean(error)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            >
                {SEPARATOR_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </Field>
    );
}

function CurrencyStatusSwitch({
    status,
    url,
}: {
    status: Status;
    url: string;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Switch
            size="sm"
            checked={status === 'active'}
            disabled={processing}
            aria-label={t('Para birimi durumunu değiştir')}
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

function CurrencySaveButton({ processing }: { processing?: boolean }) {
    return (
        <Button type="submit" disabled={processing}>
            <Save data-icon="inline-start" />
            {t('Kaydet')}
        </Button>
    );
}

export default function CurrencyIndex({ currencies, defaults }: Props) {
    return (
        <>
            <Head title={t('Para Birimleri')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Para Birimleri')}
                        description={t(
                            'Sistemde kullanılacak para birimlerini yönetin.',
                        )}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={currencyDeleted().url}>
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
                                        {t('Para birimi ekle')}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {t(
                                            'Yeni para birimi tanımı oluşturun.',
                                        )}
                                    </SheetDescription>
                                </SheetHeader>
                                <Form
                                    {...currencyStore.form()}
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
                                                <div className="grid grid-cols-2 gap-3">
                                                    <CurrencyField
                                                        name="sort_order"
                                                        label={t('Sıra No')}
                                                        type="number"
                                                        min={0}
                                                        required={false}
                                                        error={
                                                            errors.sort_order
                                                        }
                                                    />
                                                    <CurrencyField
                                                        name="code"
                                                        label={t('Kod')}
                                                        error={errors.code}
                                                    />
                                                </div>
                                                <CurrencyField
                                                    name="name"
                                                    label={t('Ad')}
                                                    error={errors.name}
                                                />
                                                <div className="grid grid-cols-2 gap-3">
                                                    <CurrencyField
                                                        name="symbol"
                                                        label={t('Sembol')}
                                                        error={errors.symbol}
                                                    />
                                                    <CurrencyField
                                                        name="decimal_places"
                                                        label={t(
                                                            'Basamak sayısı',
                                                        )}
                                                        type="number"
                                                        min={0}
                                                        defaultValue={2}
                                                        error={
                                                            errors.decimal_places
                                                        }
                                                    />
                                                    <SeparatorField
                                                        name="thousands_separator"
                                                        label={t(
                                                            'Binlik ayracı',
                                                        )}
                                                        defaultValue=","
                                                        error={
                                                            errors.thousands_separator
                                                        }
                                                    />
                                                    <SeparatorField
                                                        name="decimal_separator"
                                                        label={t(
                                                            'Onluk ayracı',
                                                        )}
                                                        defaultValue="."
                                                        error={
                                                            errors.decimal_separator
                                                        }
                                                    />
                                                </div>
                                            </FieldGroup>
                                            <div className="flex justify-end">
                                                <CurrencySaveButton
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
                                <TableHead>{t('Kod')}</TableHead>
                                <TableHead>{t('Ad')}</TableHead>
                                <TableHead>{t('Sembol')}</TableHead>
                                <TableHead>{t('Basamak')}</TableHead>
                                <TableHead>{t('Binlik ayracı')}</TableHead>
                                <TableHead>{t('Onluk ayracı')}</TableHead>
                                <TableHead className="text-right">
                                    {t('İşlem')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {currencies.data.map((currency) => (
                                <TableRow key={currency.id}>
                                    <TableCell>
                                        <CurrencyStatusSwitch
                                            status={currency.status}
                                            url={
                                                currencyStatus(currency.id).url
                                            }
                                        />
                                    </TableCell>
                                    <TableCell>{currency.sort_order}</TableCell>
                                    <TableCell className="font-medium">
                                        {currency.code}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <span>{currency.name}</span>
                                            {currency.id ===
                                            defaults.default_currency_id ? (
                                                <Badge variant="secondary">
                                                    {t('Varsayılan')}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                    <TableCell>{currency.symbol}</TableCell>
                                    <TableCell>
                                        {currency.decimal_places}
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {currency.thousands_separator ===
                                            ' '
                                                ? '␣'
                                                : currency.thousands_separator}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {currency.decimal_separator === ' '
                                                ? '␣'
                                                : currency.decimal_separator}
                                        </span>
                                    </TableCell>
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
                                                            'Para birimi düzenle',
                                                        )}
                                                    </SheetTitle>
                                                    <SheetDescription>
                                                        {t(
                                                            ':currency_name kaydını düzenleyin.',
                                                            {
                                                                currency_name:
                                                                    currency.name,
                                                            },
                                                        )}
                                                    </SheetDescription>
                                                </SheetHeader>
                                                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
                                                    <Form
                                                        {...currencyUpdate.form(
                                                            currency.id,
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
                                                                    <div className="grid grid-cols-2 gap-3">
                                                                        <CurrencyField
                                                                            name="sort_order"
                                                                            label={t(
                                                                                'Sıra No',
                                                                            )}
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            required={
                                                                                false
                                                                            }
                                                                            defaultValue={
                                                                                currency.sort_order
                                                                            }
                                                                            error={
                                                                                errors.sort_order
                                                                            }
                                                                        />
                                                                        <CurrencyField
                                                                            name="code"
                                                                            label={t(
                                                                                'Kod',
                                                                            )}
                                                                            defaultValue={
                                                                                currency.code
                                                                            }
                                                                            error={
                                                                                errors.code
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <CurrencyField
                                                                        name="name"
                                                                        label={t(
                                                                            'Ad',
                                                                        )}
                                                                        defaultValue={
                                                                            currency.name
                                                                        }
                                                                        error={
                                                                            errors.name
                                                                        }
                                                                    />
                                                                    <div className="grid grid-cols-2 gap-3">
                                                                        <CurrencyField
                                                                            name="symbol"
                                                                            label={t(
                                                                                'Sembol',
                                                                            )}
                                                                            defaultValue={
                                                                                currency.symbol
                                                                            }
                                                                            error={
                                                                                errors.symbol
                                                                            }
                                                                        />
                                                                        <CurrencyField
                                                                            name="decimal_places"
                                                                            label={t(
                                                                                'Basamak sayısı',
                                                                            )}
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            defaultValue={
                                                                                currency.decimal_places
                                                                            }
                                                                            error={
                                                                                errors.decimal_places
                                                                            }
                                                                        />
                                                                        <SeparatorField
                                                                            name="thousands_separator"
                                                                            label={t(
                                                                                'Binlik ayracı',
                                                                            )}
                                                                            defaultValue={
                                                                                currency.thousands_separator
                                                                            }
                                                                            error={
                                                                                errors.thousands_separator
                                                                            }
                                                                        />
                                                                        <SeparatorField
                                                                            name="decimal_separator"
                                                                            label={t(
                                                                                'Onluk ayracı',
                                                                            )}
                                                                            defaultValue={
                                                                                currency.decimal_separator
                                                                            }
                                                                            error={
                                                                                errors.decimal_separator
                                                                            }
                                                                        />
                                                                    </div>
                                                                </FieldGroup>
                                                                <FormActions
                                                                    destroy={
                                                                        <DeleteButton
                                                                            action={currencyDestroy(
                                                                                currency.id,
                                                                            )}
                                                                            title={t(
                                                                                ':currency_name para birimi silinsin mi?',
                                                                                {
                                                                                    currency_name:
                                                                                        currency.name,
                                                                                },
                                                                            )}
                                                                            description={t(
                                                                                'Silinen para birimi, silinenler sayfasından geri alınabilir.',
                                                                            )}
                                                                        />
                                                                    }
                                                                >
                                                                    <CurrencySaveButton
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

                            {currencies.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {t('Para birimi kaydı bulunamadı.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={currencies} showRange={false} />
            </div>
        </>
    );
}

CurrencyIndex.layout = {
    breadcrumbs: [
        {
            title: t('Para Birimleri'),
            href: dashboard(),
        },
    ],
};
