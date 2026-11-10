<?php

declare(strict_types=1);

namespace App\Support\Registry;

use App\Enums\UserType;
use App\Models\User;

/**
 * Sidebar menüsünün tipli builder'ı. Çekirdek ve modüller, ilgili alanın
 * (`panel` / `app`) `{area}.menu.register` hook'una bağlanıp buraya yazar.
 *
 * Menü, her istekte kullanıcıya göre yeniden kurulur: yetki kontrolünden geçen
 * öğeler `order`'a göre sıralanır, boş gruplar gizlenir. Sonuç `navigation`
 * paylaşılan prop'u olarak Inertia ile paylaşılır.
 */
final class MenuRegistry
{
    /**
     * @var array<string, bool>
     */
    private array $registeredAreas = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $groups = [];

    public function group(string $area, string $key, string $label, int $order = 100): self
    {
        $this->groups[$area][$key] ??= [
            'key' => $key,
            'label' => $label,
            'order' => $order,
            'items' => [],
        ];

        // Var olan grubun etiketi/sırası güncellenebilsin.
        $this->groups[$area][$key]['label'] = $label;
        $this->groups[$area][$key]['order'] = $order;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function add(string $area, string $group, array $item): self
    {
        if (! isset($item['key'])) {
            throw new \InvalidArgumentException(__('Menu item key is required.'));
        }

        $this->groups[$area][$group] ??= [
            'key' => $group,
            'label' => ucfirst($group),
            'order' => 100,
            'items' => [],
        ];

        $this->groups[$area][$group]['items'][(string) $item['key']] = [
            'order' => 100,
            'permission' => null,
            'icon' => null,
            'href' => '#',
            'items' => [],
            ...$item,
        ];

        return $this;
    }

    /**
     * Var olan bir öğenin alanlarını değiştirir (ör. modülün çekirdek öğenin
     * adresini değiştirmesi). Öğe yoksa sessizce geçer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $area, string $group, string $key, array $attributes): self
    {
        if (isset($this->groups[$area][$group]['items'][$key])) {
            $this->groups[$area][$group]['items'][$key] = [...$this->groups[$area][$group]['items'][$key], ...$attributes];
        }

        return $this;
    }

    /**
     * Daha önce eklenmiş bir öğeyi kaldırır (modül, çekirdeğin bir öğesini
     * gizlemek istediğinde). Öğe yoksa sessizce geçer.
     */
    public function remove(string $area, string $group, string $key): self
    {
        unset($this->groups[$area][$group]['items'][$key]);

        return $this;
    }

    /**
     * Var olan bir öğenin alt menüsüne öğe ekler (aynı anahtarlı alt öğe
     * varsa yerine geçer). Üst öğe yoksa sessizce geçer.
     *
     * @param  array<string, mixed>  $item
     */
    public function addChild(string $area, string $group, string $parentKey, array $item): self
    {
        if (! isset($item['key'])) {
            throw new \InvalidArgumentException(__('Menu item key is required.'));
        }

        if (! isset($this->groups[$area][$group]['items'][$parentKey])) {
            return $this;
        }

        $this->removeChild($area, $group, $parentKey, (string) $item['key']);
        $this->groups[$area][$group]['items'][$parentKey]['items'][] = ['order' => 100, 'permission' => null, 'href' => '#', ...$item];

        return $this;
    }

    /**
     * Bir öğenin alt menüsünden öğe kaldırır. Öğe yoksa sessizce geçer.
     */
    public function removeChild(string $area, string $group, string $parentKey, string $key): self
    {
        if (! isset($this->groups[$area][$group]['items'][$parentKey])) {
            return $this;
        }

        $this->groups[$area][$group]['items'][$parentKey]['items'] = array_values(array_filter(
            $this->groups[$area][$group]['items'][$parentKey]['items'] ?? [],
            fn (array $child): bool => ($child['key'] ?? null) !== $key,
        ));

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(?User $user): array
    {
        $area = $this->areaForUser($user);

        if ($area === null) {
            return [];
        }

        return $this->forArea($area, $user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forArea(string $area, ?User $user): array
    {
        $this->registerArea($area);

        return collect($this->groups[$area] ?? [])
            ->sortBy('order')
            ->map(function (array $group) use ($user): array {
                $group['items'] = collect($group['items'] ?? [])
                    ->map(fn (array $item): ?array => $this->buildAuthorizedItem($item, $user))
                    ->filter()
                    ->sortBy('order')
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group): bool => count($group['items']) > 0)
            ->values()
            ->all();
    }

    private function registerArea(string $area): void
    {
        if (($this->registeredAreas[$area] ?? false) === true) {
            return;
        }

        $this->registeredAreas[$area] = true;

        hooks()->do("{$area}.menu.register", $this);
    }

    private function areaForUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $user->user_type === UserType::Admin ? 'panel' : 'app';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function buildAuthorizedItem(array $item, ?User $user): ?array
    {
        $children = collect($item['items'] ?? [])
            ->map(fn (array $child): ?array => $this->buildAuthorizedItem($child, $user))
            ->filter()
            ->sortBy('order')
            ->values()
            ->all();

        $permission = $item['permission'] ?? null;
        $isAuthorized = $permission === null || $permission === '' || $user?->can($permission) === true;

        // Yetkisiz ve görünür alt öğesi olmayan dal gizlenir.
        if (! $isAuthorized && count($children) === 0) {
            return null;
        }

        return [
            'key' => $item['key'],
            'title' => $item['title'],
            'href' => $item['href'] ?? '#',
            'icon' => $item['icon'] ?? null,
            // Bölüm giriş sayfasındaki kartın açıklaması (isteğe bağlı).
            'description' => $item['description'] ?? null,
            'permission' => $permission,
            'order' => $item['order'] ?? 100,
            'items' => $children,
            // false: alt öğeler sidebar'da açılır menü olarak gösterilmez, öğe düz bağlantıdır.
            'collapsible' => $item['collapsible'] ?? true,
        ];
    }
}
