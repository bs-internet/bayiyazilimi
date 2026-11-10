import { Form, Head } from '@inertiajs/react';
import { CheckCheck, Save, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import RolesController from '@/actions/App/Http/Controllers/Panel/Settings/Roles/RolesController';
import { FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermissionSelection } from '@/hooks/use-permission-selection';
import { t } from '@/lib/i18n';
import { index } from '@/routes/panel/settings/roles';
import type { PermissionRow, RoleDetail } from '@/types';

type Props = {
    role: { data: RoleDetail };
    permissionGroups: Record<string, PermissionRow[]>;
    isSystem: boolean;
};

export default function ShowRole({ role, permissionGroups, isSystem }: Props) {
    const current = role.data;
    const { selected, toggle, toggleGroup, requiredLabels } =
        usePermissionSelection(permissionGroups, current.permissions);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title={current.name} />

            <div className="flex max-w-4xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold">
                            {current.name}
                        </h2>
                        {isSystem && (
                            <Badge variant="secondary">
                                {t('Sistem görevi')}
                            </Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {isSystem
                            ? t(
                                  'Sistem görevleri silinemez. İzinleri düzenleyebilirsiniz.',
                              )
                            : t('Görev adını ve izinleri güncelleyin.')}
                    </p>
                </div>

                <Form
                    {...RolesController.update.form(current.uuid)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {Array.from(selected).map((name) => (
                                <input
                                    key={name}
                                    type="hidden"
                                    name="permissions[]"
                                    value={name}
                                />
                            ))}

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Görev adı')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={current.name}
                                    required
                                    disabled={isSystem}
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="flex flex-col gap-4">
                                <Label>{t('İzinler')}</Label>
                                <InputError message={errors.permissions} />

                                {Object.entries(permissionGroups).map(
                                    ([group, rows]) => {
                                        const allSelected = rows.every((r) =>
                                            selected.has(r.name),
                                        );

                                        return (
                                            <div
                                                key={group}
                                                className="flex flex-col gap-3 rounded-lg border p-4"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <h3 className="text-sm font-semibold">
                                                        {group}
                                                    </h3>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            toggleGroup(rows)
                                                        }
                                                    >
                                                        {allSelected ? (
                                                            <X />
                                                        ) : (
                                                            <CheckCheck />
                                                        )}
                                                        {allSelected
                                                            ? t('Tümünü Kaldır')
                                                            : t('Tümünü Seç')}
                                                    </Button>
                                                </div>
                                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                    {rows.map((row) => (
                                                        <label
                                                            key={row.name}
                                                            className="flex items-start gap-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                checked={selected.has(
                                                                    row.name,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    toggle(
                                                                        row.name,
                                                                    )
                                                                }
                                                            />
                                                            <span className="flex flex-col">
                                                                <span>
                                                                    {row.label}
                                                                </span>
                                                                {requiredLabels(
                                                                    row.name,
                                                                ).length > 0 ? (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {t(
                                                                            'Gerektirir: :value',
                                                                            {
                                                                                value: requiredLabels(
                                                                                    row.name,
                                                                                ).join(
                                                                                    ', ',
                                                                                ),
                                                                            },
                                                                        )}
                                                                    </span>
                                                                ) : null}
                                                            </span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    },
                                )}
                            </div>

                            <FormActions
                                destroy={
                                    !isSystem && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() => setDeleteOpen(true)}
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            {t('Sil')}
                                        </Button>
                                    )
                                }
                            >
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <Save />}
                                    {t('Değişiklikleri kaydet')}
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Görevi sil')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                '":current_name" görevini silmek istediğinize emin misiniz? Bu göreve atanmış kullanıcılar varsa işlem engellenir.',
                                { current_name: current.name },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...RolesController.destroy.form(current.uuid)}
                        onSuccess={() => setDeleteOpen(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <X />
                                        {t('Vazgeç')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : <Trash2 />}
                                    {t('Sil')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ShowRole.layout = {
    breadcrumbs: [
        {
            title: t('Görevler'),
            href: index(),
        },
        {
            title: t('Detay'),
            href: '#',
        },
    ],
};
