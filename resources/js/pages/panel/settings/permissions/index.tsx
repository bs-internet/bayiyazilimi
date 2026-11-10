import { Head, Link, useForm } from '@inertiajs/react';
import { Archive, Pencil, Plus, Radar, Save, X } from 'lucide-react';
import { useState } from 'react';

import PermissionsController from '@/actions/App/Http/Controllers/Panel/Settings/Permissions/PermissionsController';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    deleted as deletedRoute,
    discover as discoverRoute,
} from '@/routes/panel/settings/permissions';
import type { Permission } from '@/types';

type Props = {
    groups: Record<string, Permission[]>;
};

export default function PermissionsIndex({ groups }: Props) {
    const [editing, setEditing] = useState<Permission | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Yetkiler')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 border-b pb-4 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title={t('Yetkiler')}
                        description={t(
                            'Sistemdeki izinleri tanımla, grupla ve etiketle. Rotalara karşılık gelmeyen özel izinler de buradan eklenir.',
                        )}
                    />
                    <div className="flex flex-wrap gap-2 md:shrink-0">
                        <Button asChild variant="ghost">
                            <Link href={deletedRoute().url}>
                                <Archive data-icon="inline-start" />
                                {t('Silinenler')}
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={discoverRoute().url}>
                                <Radar data-icon="inline-start" />
                                {t('Rotalardan Keşfet')}
                            </Link>
                        </Button>
                        <Button onClick={() => setCreating(true)}>
                            <Plus data-icon="inline-start" />
                            {t('Yeni İzin')}
                        </Button>
                    </div>
                </div>

                {Object.keys(groups).length === 0 ? (
                    <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {t(
                            'Henüz tanımlı izin yok. "Rotalardan Keşfet" ile mevcut panel rotalarını toplu ekleyebilir veya "Yeni İzin" ile tek tek tanımlayabilirsin.',
                        )}
                    </div>
                ) : (
                    Object.entries(groups).map(([group, items]) => (
                        <section key={group} className="flex flex-col gap-2">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                {group}
                            </h2>
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Etiket')}</TableHead>
                                            <TableHead className="font-mono">
                                                {t('İzin Adı')}
                                            </TableHead>
                                            <TableHead className="w-24">
                                                {t('Görevler')}
                                            </TableHead>
                                            <TableHead className="w-32 text-right">
                                                {t('İşlemler')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.uuid}>
                                                <TableCell>
                                                    {item.label ?? (
                                                        <span className="text-muted-foreground italic">
                                                            {t('(etiket yok)')}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary">
                                                        {item.roles_count}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setEditing(item)
                                                            }
                                                            aria-label={t(
                                                                'Düzenle',
                                                            )}
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </section>
                    ))
                )}
            </div>

            {creating ? (
                <PermissionDialog
                    mode="create"
                    onClose={() => setCreating(false)}
                />
            ) : null}

            {editing ? (
                <PermissionDialog
                    mode="edit"
                    permission={editing}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

type DialogProps =
    | { mode: 'create'; onClose: () => void }
    | { mode: 'edit'; permission: Permission; onClose: () => void };

function PermissionDialog(props: DialogProps) {
    const isEdit = props.mode === 'edit';
    const initial = isEdit
        ? {
              name: props.permission.name,
              group: props.permission.group ?? '',
              label: props.permission.label ?? '',
          }
        : { name: '', group: '', label: '' };

    const { data, setData, processing, errors, post, put, reset } =
        useForm(initial);

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit) {
            put(
                PermissionsController.update({
                    permission: props.permission.uuid,
                }).url,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                        props.onClose();
                    },
                },
            );
        } else {
            post(PermissionsController.store().url, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    props.onClose();
                },
            });
        }
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    props.onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? t('İzni düzenle') : t('Yeni izin')}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? t(
                                  'Grup ve etiket bilgisi UI metadata içindir; izin adı değiştirilemez.',
                              )
                            : t(
                                  'Rotaya bağlı bir izin için "Rotalardan Keşfet"i kullanmayı düşün.',
                              )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('İzin adı')}</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={isEdit}
                            placeholder="panel.tools.definitions.units.index"
                            className="font-mono"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="group">{t('Grup')}</Label>
                        <Input
                            id="group"
                            value={data.group}
                            onChange={(e) => setData('group', e.target.value)}
                            placeholder={t('Tanımlamalar / Birimler')}
                        />
                        <InputError message={errors.group} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="label">{t('Etiket')}</Label>
                        <Input
                            id="label"
                            value={data.label}
                            onChange={(e) => setData('label', e.target.value)}
                            placeholder={t('Listele')}
                        />
                        <InputError message={errors.label} />
                    </div>

                    <FormActions
                        destroy={
                            props.mode === 'edit' ? (
                                <DeleteButton
                                    action={PermissionsController.destroy({
                                        permission: props.permission.uuid,
                                    })}
                                    title={t('İzni sil')}
                                    description={
                                        props.permission.roles_count > 0
                                            ? t(
                                                  ':name izni silinecek ve :count görevden kaldırılacak. Geri alınamaz.',
                                                  {
                                                      name: props.permission
                                                          .name,
                                                      count: props.permission
                                                          .roles_count,
                                                  },
                                              )
                                            : t(
                                                  ':name izni silinecek. Geri alınamaz.',
                                                  {
                                                      name: props.permission
                                                          .name,
                                                  },
                                              )
                                    }
                                />
                            ) : null
                        }
                    >
                        <Button
                            type="button"
                            variant="outline"
                            onClick={props.onClose}
                        >
                            <X />
                            {t('İptal')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {isEdit ? <Save /> : <Plus />}
                            {isEdit ? t('Kaydet') : t('Ekle')}
                        </Button>
                    </FormActions>
                </form>
            </DialogContent>
        </Dialog>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: t('Yetkiler'), href: dashboard() }],
};
