<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tanıtılan ek modüllerin listesi ("Modüller" ekranı → "Ek modüller").
 *
 * Liste, Modüller sayfası her açıldığında uzaktaki JSON dosyasından
 * ({@see self::URL}) çekilir; sayfa bunu ertelenmiş prop olarak
 * alır, yani istek sayfanın açılmasını bekletmez. Ulaşılamazsa son başarılı
 * liste, o da yoksa boş liste gösterilir; içerik yalnız bu dosyadan gelir.
 */
final class ModuleCatalog
{
    /** Ek modül listesi (market.bayiyazilimi.com). Biçim: `[{"key", "name", "description", "url"}, …]`. */
    public const URL = 'https://market.bayiyazilimi.com/modules.json';

    /** Son başarılı uzak liste (bağlantı yokken gösterilir). */
    public const LAST_KEY = 'bayiyazilimi.module_catalog.last';

    /**
     * @return list<array{key: string, name: string, description: ?string, url: ?string, type: string}>
     */
    public function entries(): array
    {
        $remote = $this->fetch(self::URL);

        if ($remote !== null) {
            Cache::forever(self::LAST_KEY, $remote);

            return $remote;
        }

        /** @var list<array{key: string, name: string, description: ?string, url: ?string, type: string}>|null $last */
        $last = Cache::get(self::LAST_KEY);

        return is_array($last) ? $last : [];
    }

    /**
     * @return list<array{key: string, name: string, description: ?string, url: ?string, type: string}>|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout(3)->acceptJson()->get($url);
        } catch (Throwable) {
            return null;
        }

        $data = $response->successful() ? $response->json() : null;

        // Düz liste ya da {"modules": [...]} kabul edilir.
        if (is_array($data) && is_array($data['modules'] ?? null)) {
            $data = $data['modules'];
        }

        return is_array($data) && array_is_list($data) ? $this->normalize($data) : null;
    }

    /**
     * @param  array<mixed>  $entries
     * @return list<array{key: string, name: string, description: ?string, url: ?string, type: string}>
     */
    private function normalize(array $entries): array
    {
        $list = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null) || $entry['key'] === '') {
                continue;
            }

            $description = is_string($entry['description'] ?? null) && $entry['description'] !== '' ? $entry['description'] : null;
            $url = is_string($entry['url'] ?? null) ? $entry['url'] : null;

            $list[] = [
                'key' => $entry['key'],
                'name' => is_string($entry['name'] ?? null) && $entry['name'] !== '' ? $entry['name'] : $entry['key'],
                'description' => $description,
                // Yalnız http(s) bağlantılar (javascript: vb. sayfaya sızmasın).
                'url' => $url !== null && preg_match('#^https?://#i', $url) === 1 ? $url : null,
                // Eklenti (varsayılan) ya da tema; Temalar ekranı yalnız temaları gösterir.
                'type' => ($entry['type'] ?? null) === 'theme' ? 'theme' : 'extension',
            ];
        }

        return $list;
    }
}
