import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Save, Truck } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { StatusSwitch, TextField } from '@/components/shop/form-fields';
import { Badge } from '@/components/ui/badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { isPositive } from '@/lib/decimal';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import {
    edit,
    index,
    status as statusRoute,
    store,
} from '@/routes/panel/shop/carriers';
import type { ShopCarrier } from '@/types';

type Props = {
    carriers: ShopCarrier[];
};

export default function CarrierIndex({ carriers }: Props) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const activeCount = carriers.filter((c) => c.status === 'active').length;

    return (
        <>
            <Head title={t('Kargo Firmaları')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Kargo Firmaları')}
                    meta={t(':carriers_length firma · :active_count aktif', {
                        carriers_length: carriers.length,
                        active_count: activeCount,
                    })}
                    description={t(
                        'Sepette kargo ücreti, toplam desiye göre seçilen firmanın fiyat tablosundan hesaplanır.',
                    )}
                    actions={
                        <Button
                            onClick={() => {
                                setFormKey((k) => k + 1);
                                setOpen(true);
                            }}
                        >
                            <Plus data-icon="inline-start" />
                            {t('Yeni firma')}
                        </Button>
                    }
                />

                <TableCard>
                    {carriers.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Truck />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {t('Kargo firması yok')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Firma yokken ayarlardaki sabit kargo ücreti kullanılır.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Firma')}</TableHead>
                                    <TableHead>{t('Desi tablosu')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Ek desi')}
                                    </TableHead>
                                    <TableHead>{t('Teslim')}</TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-24">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {carriers.map((carrier) => (
                                    <TableRow key={carrier.id}>
                                        <TableCell className="min-w-60">
                                            <div className="flex items-center gap-3">
                                                {carrier.logo_url ? (
                                                    <img
                                                        src={carrier.logo_url}
                                                        alt=""
                                                        className="h-10 w-16 shrink-0 rounded-md border bg-white object-contain p-1"
                                                    />
                                                ) : (
                                                    <span className="flex h-10 w-16 shrink-0 items-center justify-center rounded-md border bg-muted">
                                                        <Truck className="size-4 text-muted-foreground" />
                                                    </span>
                                                )}
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Link
                                                            href={edit(
                                                                carrier.id,
                                                            )}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {carrier.name}
                                                        </Link>
                                                        {carrier.is_default && (
                                                            <Badge variant="secondary">
                                                                {t(
                                                                    'Varsayılan',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('Kod:')}{' '}
                                                        <span className="font-mono">
                                                            {carrier.code}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {carrier.rates_count ? (
                                                <span className="tabular-nums">
                                                    {t(':rates_count satır', {
                                                        rates_count:
                                                            carrier.rates_count,
                                                    })}
                                                </span>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-transparent bg-destructive/10 text-destructive"
                                                >
                                                    {t('Tanımsız')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {isPositive(
                                                carrier.extra_desi_price,
                                            )
                                                ? formatMoney(
                                                      carrier.extra_desi_price,
                                                  )
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {carrier.delivery_days
                                                ? t(':delivery_days gün', {
                                                      delivery_days:
                                                          carrier.delivery_days,
                                                  })
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={carrier.status}
                                                url={
                                                    statusRoute(carrier.id).url
                                                }
                                                label={t(
                                                    'Firma durumunu değiştir',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(carrier.id)}
                                                        aria-label={t(
                                                            'Düzenle',
                                                        )}
                                                    >
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                            </div>
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
                        <SheetTitle>{t('Kargo firması ekle')}</SheetTitle>
                        <SheetDescription>
                            {t(
                                "Kaydettikten sonra desi fiyat tablosunu girebilir veya Excel'den yükleyebilirsiniz.",
                            )}
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        key={formKey}
                        {...store.form()}
                        onSuccess={() => setOpen(false)}
                        className="flex flex-col gap-5 px-4 pb-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FieldGroup>
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="active"
                                    />
                                    <input
                                        type="hidden"
                                        name="prices_include_tax"
                                        value="1"
                                    />
                                    <div className="grid grid-cols-2 gap-3">
                                        <TextField
                                            name="code"
                                            label={t('Kod')}
                                            required
                                            placeholder="YURTICI"
                                            error={errors.code}
                                        />
                                        <TextField
                                            name="extra_desi_price"
                                            label={t('Ek desi ücreti (TL)')}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            defaultValue={0}
                                            error={errors.extra_desi_price}
                                        />
                                    </div>
                                    <TextField
                                        name="name"
                                        label={t('Firma adı')}
                                        required
                                        error={errors.name}
                                    />
                                    <TextField
                                        name="tracking_url"
                                        label={t('Takip bağlantısı')}
                                        placeholder="https://.../takip?no={code}"
                                        description={t(
                                            '{code} yerine takip numarası yazılır.',
                                        )}
                                        error={errors.tracking_url}
                                    />
                                </FieldGroup>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        <Save data-icon="inline-start" />
                                        {t('Kaydet')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}

CarrierIndex.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Kargo Firmaları'), href: index() },
    ],
};
