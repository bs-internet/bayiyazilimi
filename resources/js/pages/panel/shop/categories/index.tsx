import { Form, Head } from '@inertiajs/react';
import {
    CornerDownRight,
    Folder,
    FolderPlus,
    FolderTree,
    MoreHorizontal,
    Pencil,
    Plus,
    Save,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { CheckField } from '@/components/shop/check-field';
import {
    SelectField,
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
} from '@/routes/panel/shop/categories';
import type { CategoryOption, ShopCategory } from '@/types';

type Props = {
    categories: ShopCategory[];
    parents: CategoryOption[];
    /** Vitrin kurulu mu? Arama motoru (meta) alanları yalnız o zaman çıkar. */
    storefront: boolean;
};

type Editing =
    | { mode: 'edit'; category: ShopCategory }
    | { mode: 'new'; parentId: string | null }
    | null;

/**
 * Kategorinin kendisini ve alt dallarını (ağaç sırasında kendisinden sonra
 * gelen, daha derin satırlar) üst kategori seçeneklerinden çıkarır.
 */
function withoutBranch(
    parents: CategoryOption[],
    id: string,
): CategoryOption[] {
    const start = parents.findIndex((p) => p.value === id);

    if (start < 0) {
        return parents;
    }

    let end = start + 1;

    while (end < parents.length && parents[end].depth > parents[start].depth) {
        end++;
    }

    return [...parents.slice(0, start), ...parents.slice(end)];
}

/** Sheet içinde başlıklı alan grubu. */
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

function CategoryForm({
    category,
    parentId,
    parents,
    storefront,
    onDone,
}: {
    category: ShopCategory | null;
    parentId: string | null;
    parents: CategoryOption[];
    storefront: boolean;
    onDone: () => void;
}) {
    const formProps = category ? update.form(category.id) : store.form();
    const options = category ? withoutBranch(parents, category.id) : parents;

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
                            <SelectField
                                name="parent_id"
                                label={t('Üst kategori')}
                                options={options}
                                placeholder={t('— Ana kategori —')}
                                defaultValue={category?.parent_id ?? parentId}
                                error={errors.parent_id}
                            />
                            <TextField
                                name="name"
                                label={t('Ad')}
                                required
                                defaultValue={category?.name}
                                error={errors.name}
                            />
                            <TextField
                                name="slug"
                                label={t('URL (slug)')}
                                defaultValue={category?.slug}
                                error={errors.slug}
                                description={t(
                                    'Boş bırakılırsa addan üretilir.',
                                )}
                            />
                            <TextareaField
                                name="description"
                                label={t('Açıklama')}
                                rows={3}
                                defaultValue={category?.description}
                                error={errors.description}
                            />
                        </Group>

                        <Group title={t('Görsel')}>
                            <Field data-invalid={Boolean(errors.image)}>
                                <FieldLabel htmlFor="image">
                                    {t('Kategori görseli')}
                                </FieldLabel>
                                <div className="flex items-center gap-3">
                                    <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-white">
                                        {category?.image_url ? (
                                            <img
                                                src={category.image_url}
                                                alt=""
                                                className="size-full object-contain"
                                            />
                                        ) : (
                                            <Folder className="size-6 text-muted-foreground" />
                                        )}
                                    </div>
                                    <Input
                                        id="image"
                                        name="image"
                                        type="file"
                                        accept="image/*"
                                    />
                                </div>
                                <InputError message={errors.image} />
                            </Field>
                            {category?.image_url ? (
                                <CheckField
                                    name="remove_image"
                                    label={t('Mevcut görseli kaldır')}
                                />
                            ) : null}
                        </Group>

                        <Group title={t('Sıralama')}>
                            <TextField
                                name="sort_order"
                                label={t('Sıra')}
                                type="number"
                                min={0}
                                defaultValue={category?.sort_order ?? 0}
                                error={errors.sort_order}
                            />
                        </Group>

                        {storefront ? (
                            <Group title={t('Arama motoru')}>
                                <TextField
                                    name="meta_title"
                                    label={t('Meta başlık')}
                                    defaultValue={category?.meta_title}
                                    error={errors.meta_title}
                                />
                                <TextareaField
                                    name="meta_description"
                                    label={t('Meta açıklama')}
                                    rows={2}
                                    defaultValue={category?.meta_description}
                                    error={errors.meta_description}
                                />
                            </Group>
                        ) : null}
                    </div>

                    <SheetFooter className="mt-0 flex-row items-center justify-between border-t">
                        <FormActions
                            className="w-full"
                            destroy={
                                category && (
                                    <DeleteButton
                                        action={destroy(category.id)}
                                        onSuccess={onDone}
                                        title={t(
                                            ':category_name silinsin mi?',
                                            { category_name: category.name },
                                        )}
                                        description={t(
                                            'Alt kategorisi veya ürünü olan kategori silinemez.',
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

export default function CategoryIndex({
    categories,
    parents,
    storefront,
}: Props) {
    const [selected, setEditing] = useState<Editing>(null);
    const [query, setQuery] = useState('');

    // Silinen kayıt listeden düşünce düzenleme paneli kendiliğinden kapanır.
    const editing =
        selected?.mode === 'edit' &&
        !categories.some((c) => c.id === selected.category.id)
            ? null
            : selected;

    const rows = useMemo(() => {
        const term = query.trim().toLocaleLowerCase('tr');

        if (!term) {
            return categories;
        }

        return categories.filter(
            (c) =>
                c.name.toLocaleLowerCase('tr').includes(term) ||
                c.slug.includes(term),
        );
    }, [categories, query]);

    const parentName = (id: string | null) =>
        parents.find((p) => p.value === id)?.label;

    return (
        <>
            <Head title={t('Kategoriler')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Katalog') },
                        { title: t('Kategoriler') },
                    ]}
                    title={t('Kategoriler')}
                    meta={
                        <span>
                            {t(':count kategori', { count: categories.length })}
                        </span>
                    }
                    actions={
                        <Button
                            onClick={() =>
                                setEditing({ mode: 'new', parentId: null })
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {t('Yeni kategori')}
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
                                placeholder={t('Kategori ara')}
                                className="pl-9"
                                aria-label={t('Kategori ara')}
                            />
                        </div>
                    }
                >
                    {rows.length === 0 ? (
                        <Empty className="py-16">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FolderTree />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {query
                                        ? t('Eşleşen kategori yok')
                                        : t('Kategori yok')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {query
                                        ? t('Farklı bir arama deneyin.')
                                        : t('İlk kategoriyi ekleyin.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Kategori')}</TableHead>
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
                                {rows.map((category) => {
                                    const depth = query ? 0 : category.depth;

                                    return (
                                        <TableRow key={category.id}>
                                            <TableCell className="min-w-72">
                                                <div
                                                    className="flex items-center gap-3"
                                                    style={{
                                                        paddingLeft: `${depth * 1.5}rem`,
                                                    }}
                                                >
                                                    {depth > 0 && (
                                                        <CornerDownRight className="size-4 shrink-0 text-muted-foreground/60" />
                                                    )}
                                                    <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-white">
                                                        {category.image_url ? (
                                                            <img
                                                                src={
                                                                    category.image_url
                                                                }
                                                                alt=""
                                                                className="size-full object-contain"
                                                                loading="lazy"
                                                            />
                                                        ) : (
                                                            <Folder className="size-4 text-muted-foreground" />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setEditing({
                                                                        mode: 'edit',
                                                                        category,
                                                                    })
                                                                }
                                                                className={
                                                                    category.depth ===
                                                                    0
                                                                        ? 'text-left font-semibold hover:text-primary hover:underline'
                                                                        : 'text-left font-medium hover:text-primary hover:underline'
                                                                }
                                                            >
                                                                {category.name}
                                                            </button>
                                                        </div>
                                                        <div className="truncate text-xs text-muted-foreground">
                                                            {query &&
                                                            category.parent_id
                                                                ? parentName(
                                                                      category.parent_id,
                                                                  )
                                                                : `/${category.slug}`}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {category.products_count ?? 0}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {category.sort_order}
                                            </TableCell>
                                            <TableCell>
                                                <StatusSwitch
                                                    status={category.status}
                                                    url={
                                                        status(category.id).url
                                                    }
                                                    label={t(
                                                        'Kategori durumunu değiştir',
                                                    )}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
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
                                                        className="w-48"
                                                    >
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setEditing({
                                                                    mode: 'edit',
                                                                    category,
                                                                })
                                                            }
                                                        >
                                                            <Pencil />
                                                            {t('Düzenle')}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setEditing({
                                                                    mode: 'new',
                                                                    parentId:
                                                                        category.id,
                                                                })
                                                            }
                                                        >
                                                            <FolderPlus />
                                                            {t(
                                                                'Alt kategori ekle',
                                                            )}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
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
                                ? t('Kategori düzenle')
                                : t('Yeni kategori')}
                        </SheetTitle>
                        <SheetDescription>
                            {editing?.mode === 'edit'
                                ? editing.category.name
                                : editing?.parentId
                                  ? t('Üst kategori: :parent_id', {
                                        parent_id: parentName(editing.parentId),
                                    })
                                  : t(
                                        'Ana kategori ya da alt kategori oluşturun.',
                                    )}
                        </SheetDescription>
                    </SheetHeader>
                    {editing && (
                        <CategoryForm
                            key={
                                editing.mode === 'edit'
                                    ? editing.category.id
                                    : `new-${editing.parentId ?? ''}`
                            }
                            category={
                                editing.mode === 'edit'
                                    ? editing.category
                                    : null
                            }
                            parentId={
                                editing.mode === 'new' ? editing.parentId : null
                            }
                            parents={parents}
                            storefront={storefront}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

CategoryIndex.layout = {
    breadcrumbs: [{ title: t('Kategoriler'), href: index() }],
};
