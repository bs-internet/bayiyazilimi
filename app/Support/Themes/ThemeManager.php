<?php

declare(strict_types=1);

namespace App\Support\Themes;

use App\Models\Setting;
use App\Support\Modules\ModuleDiscovery;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleState;

/**
 * Mağaza ön yüzü temaları.
 *
 * Tema, `module.json`'unda `"type": "theme"` yazan bir eklenti paketidir. Yalnız
 * mağaza sayfalarını (`shop/*`) değiştirebilir: etkin temanın paketinde
 * `resources/js/pages/themes/{tema anahtarı}/shop/...tsx` varsa o sayfa açılır,
 * yoksa ön yüzü sunan eklentinin kendi (varsayılan) sayfası. Böylece bir tema
 * yalnız istediği sayfaları verebilir. Giriş ekranı ve paneller temadan etkilenmez.
 *
 * Sayfa adı Inertia'nın bileşen dönüştürücüsünde değiştirilir (AppServiceProvider);
 * tema paketinin provider'ı kendi `resources/js/pages` klasörünü
 * `inertia.pages.paths`'e ekler (eklentilerle aynı).
 */
final class ThemeManager
{
    /** Etkin temanın anahtarı (ayarlar tablosu; boş = varsayılan tema). */
    public const SETTING = 'storefront_theme';

    /** Temanın değiştirebildiği sayfalar. */
    public const SCOPE = 'shop/';

    /** @var array<string, ModuleManifest>|null */
    private ?array $themes = null;

    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleState $state,
    ) {}

    /**
     * Kurulu ve açık temalar, anahtara göre (provider sınıfı yüklenebilir olmalı).
     *
     * @return array<string, ModuleManifest>
     */
    public function installed(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $themes = [];

        foreach ($this->discovery->all() as $manifest) {
            if ($manifest->isTheme() && class_exists($manifest->provider) && $this->state->isEnabled($manifest->key)) {
                $themes[$manifest->key] = $manifest;
            }
        }

        ksort($themes);

        return $this->themes = $themes;
    }

    public function find(string $key): ?ModuleManifest
    {
        return $this->installed()[$key] ?? null;
    }

    /** Etkin tema; seçilen tema kaldırılmış ya da kapatılmışsa null (varsayılan). */
    public function active(): ?ModuleManifest
    {
        $key = Setting::get(self::SETTING);

        return $key === null || $key === '' ? null : $this->find($key);
    }

    /**
     * Inertia bileşen adı: etkin tema bu mağaza sayfasını veriyorsa onun adı
     * (`themes/{anahtar}/shop/...`), değilse olduğu gibi.
     */
    public function component(string $component): string
    {
        if (! str_starts_with($component, self::SCOPE)) {
            return $component;
        }

        $theme = $this->active();
        $base = $theme?->basePath();

        if ($theme === null || $base === null) {
            return $component;
        }

        $themed = "themes/{$theme->key}/{$component}";

        return is_file("{$base}/resources/js/pages/{$themed}.tsx") ? $themed : $component;
    }

    /** Etkin temayı kaydeder (null = varsayılan tema). */
    public function activate(?ModuleManifest $theme): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::SETTING],
            ['value' => $theme->key ?? '', 'group' => 'general'],
        );
    }

    /** Testler ve uzun süren süreçler için önbelleği boşaltır. */
    public function flush(): void
    {
        $this->themes = null;
    }
}
