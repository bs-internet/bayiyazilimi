<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use App\Models\Permission;
use App\Models\User;

/**
 * Yetkiler arası bağımlılık: bir işlem yetkisi, o bölümü görme yetkisini
 * gerektirir (ör. "Sipariş fatura bilgisini güncelle" → "Sipariş detayı" →
 * "Siparişleri listele").
 *
 * Kurallar adlandırmadan çıkarılır (`{bölüm}.{işlem}`):
 *  - index        → üst bölümün index'i (iç içe kaynaklar: şehirler → ülkeler)
 *  - show / edit / create / deleted → {bölüm}.index
 *  - update       → {bölüm}.edit | show | index (var olan ilki)
 *  - store        → {bölüm}.create | index
 *  - diğerleri    → {bölüm}.show | edit | index
 * Yalnız gerçekten var olan yetkiler gerekir. Kurala uymayanlar EXPLICIT'te ya
 * da `permissions.dependencies` filtresiyle (modüller) tanımlanır.
 *
 * Uygulandığı yerler: görev kaydı (ön koşullar eklenir), rota yetki kontrolü
 * (ön koşulu eksikse erişim yok) ve görev ekranı (işaretleme birlikte yürür).
 */
final class PermissionDependencies
{
    /**
     * Adlandırma kuralına uymayan çekirdek bağımlılıkları.
     *
     * @var array<string, array<int, string>>
     */
    private const EXPLICIT = [
        'panel.tools.cache.clear' => ['panel.tools.cache'],
        'panel.settings.general.update' => ['panel.settings.general.edit'],
        'panel.settings.general.asset.upload' => ['panel.settings.general.edit'],
        'panel.settings.general.asset.destroy' => ['panel.settings.general.edit'],
        'panel.settings.users.email.verify' => ['panel.settings.users.show'],
        'panel.settings.users.email.change' => ['panel.settings.users.show'],
    ];

    /** @var array<string, array<int, string>>|null */
    private ?array $map = null;

    /**
     * Yetki adı → doğrudan gerektirdiği yetkiler.
     *
     * @return array<string, array<int, string>>
     */
    public function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $known = array_flip(Permission::query()->pluck('name')->all());
        $explicit = hooks()->apply('permissions.dependencies', self::EXPLICIT);
        $map = [];

        foreach (array_keys($known) as $name) {
            $requires = $explicit[$name] ?? $this->byConvention($name, $known);
            $requires = array_values(array_filter(
                array_unique($requires),
                fn (string $r): bool => $r !== $name && isset($known[$r]),
            ));

            if ($requires !== []) {
                $map[$name] = $requires;
            }
        }

        return $this->map = $map;
    }

    /**
     * @return array<int, string>
     */
    public function requires(string $name): array
    {
        return $this->map()[$name] ?? [];
    }

    /**
     * Yetkiler + tüm (dolaylı) ön koşulları.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function withPrerequisites(array $names): array
    {
        $result = [];
        $stack = array_values($names);

        while ($stack !== []) {
            $name = array_pop($stack);

            if (isset($result[$name])) {
                continue;
            }

            $result[$name] = true;

            foreach ($this->requires($name) as $required) {
                $stack[] = $required;
            }
        }

        return array_keys($result);
    }

    /**
     * Kullanıcının bu yetkinin tüm ön koşullarına sahip olup olmadığı.
     */
    public function satisfiedBy(User $user, string $name): bool
    {
        foreach ($this->withPrerequisites([$name]) as $required) {
            if ($required !== $name && ! $user->can($required)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $known
     * @return array<int, string>
     */
    private function byConvention(string $name, array $known): array
    {
        $dot = strrpos($name, '.');

        if ($dot === false) {
            return [];
        }

        $section = substr($name, 0, $dot);
        $action = substr($name, $dot + 1);
        $first = function (array $candidates) use ($section, $known): array {
            foreach ($candidates as $candidate) {
                if (isset($known["{$section}.{$candidate}"])) {
                    return ["{$section}.{$candidate}"];
                }
            }

            return [];
        };

        return match ($action) {
            'index' => $this->parentIndex($section, $known),
            'show', 'edit', 'create', 'deleted' => $first(['index']),
            'update' => $first(['edit', 'show', 'index']),
            'store' => $first(['create', 'index']),
            default => $first(['show', 'edit', 'index']),
        };
    }

    /**
     * İç içe kaynak: `….countries.cities.index` → `….countries.index`.
     *
     * @param  array<string, int>  $known
     * @return array<int, string>
     */
    private function parentIndex(string $section, array $known): array
    {
        $dot = strrpos($section, '.');

        if ($dot === false) {
            return [];
        }

        $parent = substr($section, 0, $dot).'.index';

        return isset($known[$parent]) ? [$parent] : [];
    }
}
