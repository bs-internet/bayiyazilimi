<?php

declare(strict_types=1);

namespace App\Services\Panel\Settings\Roles;

use App\Events\Panel\Settings\Roles\RoleCreatedEvent;
use App\Events\Panel\Settings\Roles\RoleDeletedEvent;
use App\Events\Panel\Settings\Roles\RolePermissionsSyncedEvent;
use App\Events\Panel\Settings\Roles\RoleUpdatedEvent;
use App\Events\Panel\Settings\Roles\UserRoleAssignedEvent;
use App\Events\Panel\Settings\Roles\UserRoleRevokedEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Pagination;
use App\Support\Permissions\PermissionDependencies;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RoleService
{
    /**
     * Kilitli sistem rolleri: UI'dan silinemez/yeniden adlandırılamaz.
     * Super Admin Gate::before ile her yetkiye sahiptir.
     */
    public const SUPER_ADMIN = 'Super Admin';

    public const SYSTEM_ROLES = [self::SUPER_ADMIN, 'Admin'];

    /**
     * @param  array{q?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['q'] ?? '';
        $escaped = addcslashes($search, '%_\\');

        return Role::query()
            ->withCount(['permissions', 'users'])
            ->where('name', '!=', 'Super Admin') // Super Admin UI listesinde gizli.
            ->when($search !== '', function ($query) use ($escaped) {
                $query->where('name', 'like', '%'.$escaped.'%');
            })
            ->orderBy('name')
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Atanabilir roller. Super Admin hiçbir yerde (Super Admin için bile)
     * listelenmez; yalnızca seeder/konsol ile atanır. Süper admin dışındaki
     * kullanıcılar ayrıca diğer sistem rollerini (Admin) de atayamaz.
     *
     * @return Collection<int, Role>
     */
    public function assignableRolesFor(User $causer): Collection
    {
        $roles = Role::query()
            ->where('name', '!=', 'Super Admin')
            ->orderBy('name')
            ->get();

        if ($causer->hasRole('Super Admin')) {
            return $roles->values();
        }

        return $roles->reject(fn (Role $role): bool => in_array($role->name, self::SYSTEM_ROLES, true))->values();
    }

    /**
     * UI gruplandırması için izinleri grup başlıklarına böl. Metadata DB'deki
     * Permission satırlarından okunur (group / label kolonları). Null group →
     * "Diğer"; null label → permission adı.
     *
     * @return array<string, array<int, array{name: string, label: string}>>
     */
    public function permissionGroups(): array
    {
        $permissions = Permission::query()->orderBy('group')->orderBy('name')->get();
        $dependencies = app(PermissionDependencies::class);

        $groups = [];
        foreach ($permissions as $permission) {
            $group = $permission->group !== null && $permission->group !== ''
                ? $permission->group
                : __('Diğer');

            $groups[$group][] = [
                'name' => $permission->name,
                // Doğrudan ön koşullar (görev ekranında işaretleme birlikte yürür).
                'requires' => $dependencies->requires($permission->name),
                'label' => $permission->label !== null && $permission->label !== ''
                    ? $permission->label
                    : $permission->name,
            ];
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param  array{name: string, permissions: array<int, string>}  $data
     */
    public function create(array $data, User $causer): Role
    {
        return DB::transaction(function () use ($data, $causer): Role {
            $role = Role::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $permissions = $this->resolvePermissions($data['permissions']);
            $role->syncPermissions($permissions);

            RoleCreatedEvent::dispatch(
                $role,
                $causer,
                $permissions->pluck('name')->all(),
            );

            return $role;
        });
    }

    /**
     * @param  array{name?: string, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data, User $causer): Role
    {
        return DB::transaction(function () use ($role, $data, $causer): Role {
            $changes = [];

            if (isset($data['name']) && $data['name'] !== $role->name) {
                $changes['name'] = ['from' => $role->name, 'to' => $data['name']];
                $role->forceFill(['name' => $data['name']])->save();
            }

            if (array_key_exists('permissions', $data)) {
                $current = $role->permissions()->pluck('name')->all();

                $permissions = $this->resolvePermissions($data['permissions']);
                $role->syncPermissions($permissions);
                $next = $permissions->pluck('name')->all();

                $added = array_values(array_diff($next, $current));
                $removed = array_values(array_diff($current, $next));

                if ($added !== [] || $removed !== []) {
                    RolePermissionsSyncedEvent::dispatch($role, $causer, $added, $removed);
                }
            }

            if ($changes !== []) {
                RoleUpdatedEvent::dispatch($role, $causer, $changes);
            }

            return $role;
        });
    }

    public function delete(Role $role, User $causer): void
    {
        if ($this->isSystemRole($role)) {
            throw new RuntimeException(__('Sistem görevleri silinemez.'));
        }

        if ($role->users()->count() > 0) {
            throw new RuntimeException(__('Bu göreve atanmış kullanıcılar olduğu için silinemiyor.'));
        }

        $name = $role->name;

        DB::transaction(function () use ($role): void {
            $role->delete();
        });

        RoleDeletedEvent::dispatch($name, $causer);
    }

    public function assignToUser(User $user, User $causer, string $roleName): User
    {
        return DB::transaction(function () use ($user, $causer, $roleName): User {
            $previous = $user->roles()->first()?->name;

            // Super Admin rolü panelden verilmez ve geri alınmaz (yalnız kurulum / konsol).
            // Bir Admin, Super Admin'i başka role çekerek yetkisini düşüremez; kendi rolünü de değiştiremez.
            if ($user->hasRole(self::SUPER_ADMIN) && $roleName !== self::SUPER_ADMIN) {
                throw ValidationException::withMessages(['role' => __('Super Admin kullanıcısının rolü panelden değiştirilemez.')]);
            }

            if ($user->is($causer) && $previous !== $roleName) {
                throw ValidationException::withMessages(['role' => __('Kendi rolünüzü değiştiremezsiniz.')]);
            }

            if ($roleName === self::SUPER_ADMIN || ! $this->assignableRolesFor($causer)->contains('name', $roleName)) {
                throw ValidationException::withMessages(['role' => __('Bu rolü atama yetkiniz yok.')]);
            }

            $user->syncRoles([$roleName]);

            UserRoleAssignedEvent::dispatch($user, $causer, $roleName, $previous);

            if ($previous !== null && $previous !== $roleName) {
                UserRoleRevokedEvent::dispatch($user, $causer, $previous);
            }

            return $user;
        });
    }

    public function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $names): Collection
    {
        if ($names === []) {
            return collect();
        }

        // Bir işlem yetkisi, bölümü görme yetkisini gerektirir; ön koşullar eklenir.
        $names = app(PermissionDependencies::class)->withPrerequisites($names);

        return Permission::query()->whereIn('name', $names)->get();
    }
}
