<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Collection;

/**
 * Laravel'in paket keşfi (auto-discovery), kapalı modüller hariç.
 *
 * Panelden kapatılan bir modülün paketi tümüyle atlanır: provider'ları ve
 * alias'ları kaydedilmez. Önbellekteki `bootstrap/cache/packages.php`
 * değişmez (`package:discover` her zaman tüm paketleri yazar); süzme okuma
 * anında yapılır, bu yüzden modülü açıp kapatmak önbellek temizliği
 * gerektirmez. `bootstrap/app.php` bu sınıfı çekirdeğinkinin yerine bağlar.
 */
class ModulePackageManifest extends PackageManifest
{
    public function __construct(Filesystem $files, string $basePath, string $manifestPath, private readonly ModuleState $state)
    {
        parent::__construct($files, $basePath, $manifestPath);
    }

    /**
     * @param  string  $key
     * @return array<int|string, mixed>
     */
    public function config($key)
    {
        // Rota yardımcıları (Wayfinder) kapalı modüller dahil üretilir: modülün
        // sayfaları derlemede (npm run build) yer almaya devam eder ve kendi
        // rotalarını içe aktarır; yardımcılar eksikse derleme kırılır. Yardımcı
        // yalnız adres üretir, kapalı modülün rotası çalışma anında yine yoktur.
        $disabled = self::generatingRouteHelpers() ? [] : array_values($this->state->disabled());

        return $this->enabledPackages($disabled)
            ->flatMap(fn (array $configuration) => (array) ($configuration[$key] ?? []))
            ->filter()
            ->all();
    }

    /**
     * Kurulu ve açık modül paketleri (ör. `bsinternet/vitrin`). Ön yüz bu listeyle
     * kapalı modüllerin derlemede kalan parçalarını (yuva kayıtları) gizler.
     *
     * @param  list<string>  $vendors
     * @return list<string>
     */
    public function activeModulePackages(array $vendors): array
    {
        return $this->enabledPackages(array_values($this->state->disabled()))
            ->keys()
            ->filter(fn (int|string $package): bool => in_array(strstr((string) $package, '/', true), $vendors, true))
            ->map(fn (int|string $package): string => (string) $package)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $disabledProviders
     * @return Collection<int|string, array<string, mixed>>
     */
    private function enabledPackages(array $disabledProviders): Collection
    {
        return (new Collection($this->getManifest()))
            ->reject(fn (array $configuration): bool => array_intersect((array) ($configuration['providers'] ?? []), $disabledProviders) !== []);
    }

    private static function generatingRouteHelpers(): bool
    {
        return PHP_SAPI === 'cli' && in_array('wayfinder:generate', (array) ($_SERVER['argv'] ?? []), true);
    }
}
