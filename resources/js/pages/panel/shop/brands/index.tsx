import { Form, Head } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Save, Search, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { CheckField } from '@/components/shop/check-field';
import {
    StatusSwitch,
    TextareaField,
    TextField,
} from '@/components/shop/form-fields';
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
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
    status,
    store,
    update,
} from '@/routes/panel/shop/brands';
import type { ShopBrand } from '@/types';

type Props = {
    brands: ShopBrand[];
};

type Editing = { mode: 'edit'; brand: ShopBrand } | { mode: 'new' } | null;

function Group({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-4 border-b px-5 py-5 last:border-b-0">
            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            <FieldGroup className="gap-4">{children}</FieldGroup>
        </section>
    );
}

function BrandLogo({ brand, size }: { brand: ShopBrand | null; size: string }) {
    return (
        <div
            className={`flex shrink-0 items-center justify-center overflow-hidden rounded-md border bg-white p-1 ${size}`}
        >
            {brand?.logo_url ? (
                <img
                    src={brand.logo_url}
                    alt=""
                    className="max-h-full max-w-full object-contain"
                    loading="lazy"
                />
            ) : (
                <span className="text-sm font-semibold text-muted-foreground uppercase">
                    {brand?.name.slice(0, 2) ?? <Tags className="size-4" />}
                </span>
            )}
        </div>
    );
}

function BrandForm({
    brand,
    onDone,
}: {
    brand: ShopBrand | null;
    onDone: () => void;
}) {
    const formProps = brand ? update.form(brand.id) : store.form();

    return (
        <Form
            {...formProps}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex min-h-0 flex-1 flex-col"
        >
            {({ processing, errors }) => (
                <>
                    <div className="min-h-0 flex-1 overflow-y-auto border-t">
                        <Group title={t('Genel')}>
                            <TextField
                                name="name"
                                label={t('Ad')}
                                required
                                defaultValue={brand?.name}
                                error={errors.name}
                            />
                            <TextField
                                name="slug"
                                label={t('URL (slug)')}
                                defaultValue={brand?.slug}
                                error={errors.slug}
                                description={t(
                                    'Boş bırakılırsa addan üretilir.',
                                )}
                            />
                            <TextareaField
                                name="description"
                                label={t('Açıklama')}
                                rows={3}
                                defaultValue={brand?.description}
                                error={errors.description}
                            />
                        </Group>

                        <Group title={t('Logo')}>
                            <Field data-invalid={Boolean(errors.logo)}>
                                <FieldLabel htmlFor="logo">
                                    {t('Logo dosyası')}
                                </FieldLabel>
                                <div className="flex items-center gap-3">
                                    <BrandLogo brand={brand} size="h-16 w-24" />
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/*"
                                    />
                                </div>
                                <InputError message={errors.logo} />
                            </Field>
                            {brand?.logo_url ? (
                                <CheckField
                                    name="remove_logo"
                                    label={t('Mevcut logoyu kaldır')}
                                />
                            ) : null}
                        </Group>

                        <Group title={t('Sıralama')}>
                            <TextField
                                name="sort_order"
                                label={t('Sıra')}
                                type="number"
                                min={0}
                                defaultValue={brand?.sort_order ?? 0}
                                error={errors.sort_order}
                                className="max-w-40"
                            />
                        </Group>
                    </div>

                    <SheetFooter className="mt-0 flex-row items-center justify-between border-t">
                        <FormActions
                            className="w-full"
                            destroy={
                                brand && (
                                    <DeleteButton
                                        action={destroy(brand.id)}
                                        onSuccess={onDone}
                                        title={t(':brand_name silinsin mi?', {
                                            brand_name: brand.name,
                                        })}
                                        description={t(
                                            'Ürünü olan marka silinemez; pasifleştirebilirsiniz.',
                                        )}
                                    />
                                )
                            }
                        >
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <Save data-icon="inline-start" />
                                )}
                                {t('Kaydet')}
                            </Button>
                        </FormActions>
                    </SheetFooter>
                </>
            )}
        </Form>
    );
}

export default function BrandIndex({ brands }: Props) {
    const [selected, setEditing] = useState<Editing>(null);
    const [query, setQuery] = useState('');

    const editing =
        selected?.mode === 'edit' &&
        !brands.some((b) => b.id === selected.brand.id)
            ? null
            : selected;

    const rows = useMemo(() => {
        const term = query.trim().toLocaleLowerCase('tr');

        return term
            ? brands.filter((b) =>
                  b.name.toLocaleLowerCase('tr').includes(term),
              )
            : brands;
    }, [brands, query]);

    return (
        <>
            <Head title={t('Markalar')} />

            <Page>
                <PageTitle
                    trail={[{ title: t('Katalog') }, { title: t('Markalar') }]}
                    title={t('Markalar')}
                    meta={
                        <span>
                            {t(':count marka', { count: brands.length })}
                        </span>
                    }
                    actions={
                        <Button onClick={() => setEditing({ mode: 'new' })}>
                            <Plus data-icon="inline-start" />
                            {t('Yeni marka')}
                        </Button>
                    }
                />

                <TableCard
                    toolbar={
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={t('Marka ara')}
                                className="pl-9"
                                aria-label={t('Marka ara')}
                            />
                        </div>
                    }
                >
                    {rows.length === 0 ? (
                        <Empty className="py-16">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Tags />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {query
                                        ? t('Eşleşen marka yok')
                                        : t('Marka yok')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {query
                                        ? t('Farklı bir arama deneyin.')
                                        : t('İlk markayı ekleyin.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Marka')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Ürün')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Sıra')}
                                    </TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-10">
                                        <span className="sr-only">
                                            {t('İşlem')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((brand) => (
                                    <TableRow key={brand.id}>
                                        <TableCell className="min-w-64">
                                            <div className="flex items-center gap-3">
                                                <BrandLogo
                                                    brand={brand}
                                                    size="h-10 w-16"
                                                />
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setEditing({
                                                                    mode: 'edit',
                                                                    brand,
                                                                })
                                                            }
                                                            className="text-left font-medium hover:text-primary hover:underline"
                                                        >
                                                            {brand.name}
                                                        </button>
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        /{brand.slug}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {brand.products_count ?? 0}
                                        </TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {brand.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={brand.status}
                                                url={status(brand.id).url}
                                                label={t(
                                                    'Marka durumunu değiştir',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={t(
                                                            'İşlemler',
                                                        )}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                    className="w-44"
                                                >
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setEditing({
                                                                mode: 'edit',
                                                                brand,
                                                            })
                                                        }
                                                    >
                                                        <Pencil />
                                                        {t('Düzenle')}
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

            <Sheet
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <SheetContent className="gap-0 sm:max-w-lg">
                    <SheetHeader className="px-5 py-4">
                        <SheetTitle>
                            {editing?.mode === 'edit'
                                ? t('Marka düzenle')
                                : t('Yeni marka')}
                        </SheetTitle>
                        <SheetDescription>
                            {editing?.mode === 'edit'
                                ? editing.brand.name
                                : t('Yeni marka oluşturun.')}
                        </SheetDescription>
                    </SheetHeader>
                    {editing && (
                        <BrandForm
                            key={
                                editing.mode === 'edit'
                                    ? editing.brand.id
                                    : 'new'
                            }
                            brand={
                                editing.mode === 'edit' ? editing.brand : null
                            }
                            onDone={() => setEditing(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

BrandIndex.layout = {
    breadcrumbs: [{ title: t('Markalar'), href: index() }],
};
