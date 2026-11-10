<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inertia sayfasının Vite giriş yolu. Sayfa çekirdekte (`resources/js/pages`)
 * yoksa modüllerin (`config('bayiyazilimi.module_vendors')`) kendi `resources/js/pages`
 * klasörlerinde aranır (bkz. inertia-config.tsx `resolve`). Çekirdekte aynı yolda
 * bir sayfa varsa modülünkünün yerine o kullanılır (müşteriye özel tasarım). Modül vendor/ altına symlink'li
 * olabildiğinden Vite manifest'teki gerçek yol kullanılır.
 */
final class InertiaPage
{
    public static function entry(string $component): string
    {
        $core = "resources/js/pages/{$component}.tsx";

        if (is_file(base_path($core))) {
            return $core;
        }

        $base = str_replace('\\', '/', base_path()).'/';

        foreach ((array) config('bayiyazilimi.module_vendors', ['bsinternet']) as $vendor) {
            foreach (glob(base_path("vendor/{$vendor}/*/resources/js/pages/{$component}.tsx")) ?: [] as $file) {
                $real = str_replace('\\', '/', realpath($file) ?: $file);

                return str_starts_with($real, $base) ? substr($real, strlen($base)) : $real;
            }
        }

        return $core;
    }
}
