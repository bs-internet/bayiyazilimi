import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import RolesController from '@/actions/App/Http/Controllers/Panel/Settings/Roles/RolesController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermissionSelection } from '@/hooks/use-permission-selection';
import { t } from '@/lib/i18n';
import { create, index } from '@/routes/panel/settings/roles';
import type { PermissionRow } from '@/types';

type Props = {
    permissionGroups: Record<string, PermissionRow[]>;
};

export default function CreateRole({ permissionGroups }: Props) {
    const { selected, toggle, toggleGroup, requiredLabels } =
        usePermissionSelection(permissionGroups);

    return (
        <>
            <Head title={t('Yeni Görev')} />

            <div className="flex max-w-4xl flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg font-semibold">{t('Yeni Görev')}</h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Görev adını belirleyin ve atanacak izinleri seçin.',
                        )}
                    </p>
                </div>

                <Form
                    {...RolesController.store.form()}
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
                                    required
                                    placeholder={t('Editor')}
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

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <Plus />}
                                    {t('Görev oluştur')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateRole.layout = {
    breadcrumbs: [
        {
            title: t('Görevler'),
            href: index(),
        },
        {
            title: t('Yeni Görev'),
            href: create(),
        },
    ],
};
