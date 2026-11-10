<?php

declare(strict_types=1);

namespace App\Services\Panel\Settings\Modules;

use App\Events\Panel\Settings\Modules\ModuleDisabledEvent;
use App\Events\Panel\Settings\Modules\ModuleEnabledEvent;
use App\Exceptions\ModuleGuardException;
use App\Models\InstalledModule;
use App\Models\User;
use App\Support\Modules\ModuleCatalog;
use App\Support\Modules\ModuleDiscovery;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Facades\Artisan;

/**
 * "Modüller" ekranının iş kuralları: kurulu modülleri listeler, açar / kapatır.
 *
 * Kurulu = module.json'u bulunan ve provider sınıfı composer ile yüklenebilen
 * modül. Kapatmak veriyi silmez; modülün paketi bir sonraki istekten itibaren
 * auto-discovery'de atlanır (bkz. ModulePackageManifest).
 *
 * Modüller arası bağımlılık module.json `requires` alanından okunur: platform
 * (`php`, `laravel`, `bayiyazilimi`, `ext-*`, `vendor/paket`) dışındaki her anahtar
 * bir modül anahtarıdır (ör. `"vitrin": "^1.0"`).
 */
final class ModuleService
{
    /** `requires` içinde modül olmayan (platform) anahtarlar. */
    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleState $state,
        private readonly ModuleCatalog $catalog,
    ) {}

    /**
     * Kurulu modüller. `setup`: kurulum kaydına göre durum —
     * `done` (bayi:install bu sürümle çalıştı), `pending` (composer ile
     * eklenmiş ama bayi:install çalışmamış), `update` (yeni sürüm gelmiş,
     * kurulumu — migration vb. — yeniden çalıştırılmamış).
     *
     * @return list<array{key: string, name: string, description: ?string, version: string, author: ?string, enabled: bool, installed_at: ?string, setup: string, requires: list<string>, required_by: list<string>}>
     */
    public function installed(): array
    {
        $modules = $this->installedManifests();
        $names = array_map(fn (ModuleManifest $m): string => $m->name, $modules);
        $records = InstalledModule::query()->whereIn('key', array_keys($modules))->get()->keyBy('key');

        $list = array_map(function (ModuleManifest $m) use ($names, $records): array {
            /** @var InstalledModule|null $record */
            $record = $records->get($m->key);

            return [
                'key' => $m->key,
                'name' => $m->name,
                'description' => $m->description,
                'version' => $m->version,
                'author' => isset($m->author['name']) ? (string) $m->author['name'] : null,
                'enabled' => $this->state->isEnabled($m->key),
                'installed_at' => $record?->installed_at->toIso8601String(),
                'setup' => match (true) {
                    $record === null => 'pending',
                    $record->version !== $m->version => 'update',
                    default => 'done',
                },
                'requires' => array_map(fn (string $key): string => $names[$key] ?? $key, $this->dependencies($m)),
                'required_by' => array_map(fn (ModuleManifest $d): string => $d->name, $this->dependents($m->key)),
            ];
        }, $modules);

        usort($list, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * Tanıtılan modüller (bkz. ModuleCatalog); kurulu olanlar işaretlenir.
     *
     * @param  string  $type  ModuleManifest::TYPE_EXTENSION ya da TYPE_THEME
     * @return list<array{key: string, name: string, description: ?string, url: ?string, type: string, installed: bool}>
     */
    public function catalog(string $type = ModuleManifest::TYPE_EXTENSION): array
    {
        $installed = $this->installedManifests(themes: true);

        return array_values(array_map(
            fn (array $entry): array => [...$entry, 'installed' => isset($installed[$entry['key']])],
            array_filter($this->catalog->entries(), fn (array $entry): bool => ($entry['type'] ?? ModuleManifest::TYPE_EXTENSION) === $type),
        ));
    }

    public function find(string $key): ?ModuleManifest
    {
        return $this->installedManifests()[$key] ?? null;
    }

    public function setEnabled(ModuleManifest $module, bool $enabled, User $causer): void
    {
        if ($this->state->isEnabled($module->key) === $enabled) {
            return;
        }

        $enabled ? $this->enable($module) : $this->disable($module);

        // Önbelleğe alınmış rotalar kapalı modülün rotalarını da içerir.
        if (app()->routesAreCached()) {
            Artisan::call('route:clear');
        }

        $enabled
            ? event(new ModuleEnabledEvent($module->key, $module->name, $causer))
            : event(new ModuleDisabledEvent($module->key, $module->name, $causer));
    }

    /**
     * Bağımlılık denetimi durum dosyasının kilidi altında, güncel kapalı listesiyle
     * yapılır: A'yı (B'ye bağlı) açmak ile B'yi kapatmak aynı anda geçemez.
     */
    private function enable(ModuleManifest $module): void
    {
        $installed = $this->installedManifests();

        $this->state->update(function (array $disabled) use ($module, $installed): array {
            $missing = array_filter(
                $this->dependencies($module),
                fn (string $key): bool => ! isset($installed[$key]) || array_key_exists($key, $disabled),
            );

            if ($missing !== []) {
                throw new ModuleGuardException(__(':name eklentisi için önce şu eklentiler açılmalı: :modules.', [
                    'name' => $module->name,
                    'modules' => implode(', ', array_map(fn (string $key): string => isset($installed[$key]) ? $installed[$key]->name : $key, $missing)),
                ]));
            }

            unset($disabled[$module->key]);

            return $disabled;
        });
    }

    private function disable(ModuleManifest $module): void
    {
        $this->state->update(function (array $disabled) use ($module): array {
            $dependents = array_filter($this->dependents($module->key), fn (ModuleManifest $d): bool => ! array_key_exists($d->key, $disabled));

            if ($dependents !== []) {
                throw new ModuleGuardException(__(':name eklentisini şu eklentiler kullanıyor: :modules. Önce onları kapatın.', [
                    'name' => $module->name,
                    'modules' => implode(', ', array_map(fn (ModuleManifest $d): string => $d->name, $dependents)),
                ]));
            }

            return [...$disabled, $module->key => $module->provider];
        });
    }

    /**
     * Kurulu modüller, anahtara göre. Temalar Eklentiler ekranında değil, Temalar
     * ekranında (ThemeManager) listelenir.
     *
     * @return array<string, ModuleManifest>
     */
    private function installedManifests(bool $themes = false): array
    {
        $modules = [];

        foreach ($this->discovery->all() as $manifest) {
            if (class_exists($manifest->provider) && ($themes || ! $manifest->isTheme())) {
                $modules[$manifest->key] = $manifest;
            }
        }

        return $modules;
    }

    /**
     * Modülün ihtiyaç duyduğu diğer modüllerin anahtarları.
     *
     * @return list<string>
     */
    private function dependencies(ModuleManifest $module): array
    {
        return $module->moduleDependencies();
    }

    /**
     * Bu modüle ihtiyaç duyan kurulu modüller.
     *
     * @return list<ModuleManifest>
     */
    private function dependents(string $key): array
    {
        return array_values(array_filter(
            $this->installedManifests(),
            fn (ModuleManifest $m): bool => in_array($key, $this->dependencies($m), true),
        ));
    }
}
