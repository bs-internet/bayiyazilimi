<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Çeviri kataloğu: bir kaynağın (çekirdek ya da bir modül) çevrilebilir metinleri.
 *
 * Kaynak dil Türkçedir; anahtar Türkçe metnin kendisidir. PHP / Blade'de
 * `__()`, `trans()`, `trans_choice()`, `@lang()`, TS/TSX'te `t()` çağrılarındaki
 * sabit metinler toplanır ve `{lang}/tr.json`'a yazılır (değer = anahtar).
 * Başka dil için aynı klasöre `{locale}.json` eklenir.
 *
 * Katalog başına bir dosya: çekirdek `lang/tr.json`; her modül kendi
 * `lang/tr.json` dosyasını taşır (modül sağlayıcısı
 * `loadJsonTranslationsFrom` ile yükler) — modül kaldırılınca çevirisi de gider.
 *
 * Kaynakta karşılığı olmayan ama çevrilmiş (anahtar ≠ değer) girdiler korunur
 * (ör. Fortify'ın İngilizce iletilerinin Türkçesi).
 */
final class TranslationCatalog
{
    /** Çekirdeğin taradığı klasörler. */
    private const CORE_SOURCES = ['app', 'routes', 'resources/views', 'resources/js'];

    /** Modülün taradığı klasörler (paket köküne göre). */
    private const MODULE_SOURCES = ['src', 'routes', 'resources'];

    /** Taranmayan yollar: üretilen kodlar, testler, shadcn ilkel bileşenleri. */
    private const EXCLUDE = [
        'resources/js/actions', 'resources/js/routes', 'resources/js/wayfinder',
        'resources/js/components/ui', 'tests', 'node_modules', 'vendor',
    ];

    /**
     * @param  string  $root  kaynağın kök klasörü (mutlak)
     * @param  list<string>  $sources  root'a göre taranacak klasörler
     */
    public function __construct(
        public readonly string $name,
        private readonly string $root,
        private readonly array $sources,
    ) {}

    /**
     * Projedeki tüm kataloglar: çekirdek + kurulu her modül (`vendor/{modül vendor'u}/*`).
     *
     * @return list<self>
     */
    public static function all(string $base): array
    {
        $catalogs = [new self('core', $base, self::CORE_SOURCES)];

        foreach ((array) config('bayiyazilimi.module_vendors', []) as $vendor) {
            $dir = $base.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$vendor;

            foreach (is_dir($dir) ? File::directories($dir) : [] as $module) {
                if (is_dir($module.DIRECTORY_SEPARATOR.'lang')) {
                    $catalogs[] = new self(basename($module), $module, self::MODULE_SOURCES);
                }
            }
        }

        return $catalogs;
    }

    /** Katalog dosyası (proje köküne göre gösterim için). */
    public function file(string $code = 'tr'): string
    {
        return $this->root.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.$code.'.json';
    }

    /**
     * Kaynaktaki tüm anahtarlar (sıralı, tekil).
     *
     * @return list<string>
     */
    public function scan(): array
    {
        $keys = [];

        foreach ($this->sources as $dir) {
            $path = $this->root.DIRECTORY_SEPARATOR.$dir;

            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (File::allFiles($path) as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));

                if ($this->excluded($relative)) {
                    continue;
                }

                $type = match (true) {
                    str_ends_with($relative, '.php') => 'php',
                    (bool) preg_match('/\.(ts|tsx)$/', $relative) => 'ts',
                    default => null,
                };

                if ($type === null) {
                    continue;
                }

                foreach (self::extract($file->getContents(), $type) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        $keys = array_map('strval', array_keys($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * Bir kaynak metindeki çeviri anahtarları.
     *
     * @return list<string>
     */
    public static function extract(string $code, string $type): array
    {
        $pattern = $type === 'ts'
            // t('…') / t("…") / t(`…`) — nesne yöntemi (x.t()) ya da başka ad değil.
            ? '/(?<![\w.$])t\(\s*([\'"`])((?:\\\\.|(?!\1).)*?)\1\s*[,)]/s'
            : '/(?:(?<![\w>:$])(?:__|trans|trans_choice)|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*[,)]/s';

        preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

        $keys = [];

        foreach ($matches as [, $quote, $raw]) {
            // Değişken içeren metin sabit anahtar değildir.
            if (($quote === '`' && str_contains($raw, '${')) || ($quote === '"' && $type === 'php' && preg_match('/(?<!\\\\)\$[A-Za-z_{]/', $raw))) {
                continue;
            }

            $key = self::unescape($raw, $quote);

            // Dosya tabanlı çeviri anahtarı (`auth.failed`, `paket::grup.anahtar`) JSON kataloğuna
            // girmemeli: Laravel önce JSON'a bakar, anahtarın kendisini döndürürdü.
            if (preg_match('/^(?:[\w-]+::)?[a-z][\w-]*\.[\w.-]+$/', $key) === 1) {
                continue;
            }

            if (trim($key) !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Katalog güncellemesi: [yeni katalog, eklenenler, çıkarılanlar].
     *
     * @return array{0: array<string, string>, 1: list<string>, 2: list<string>}
     */
    public function plan(): array
    {
        $current = $this->read('tr');
        $next = [];

        foreach ($this->scan() as $key) {
            $next[$key] = $current[$key] ?? $key;
        }

        // Kaynakta görünmeyen ama gerçekten çevrilmiş girdiler (ör. paket iletileri) kalır.
        foreach ($current as $key => $value) {
            if (! isset($next[$key]) && $value !== $key) {
                $next[$key] = $value;
            }
        }

        ksort($next, SORT_STRING);

        $added = array_values(array_diff(array_keys($next), array_keys($current)));
        $removed = array_values(array_diff(array_keys($current), array_keys($next)));

        return [$next, array_map('strval', $added), array_map('strval', $removed)];
    }

    /**
     * Kataloğu yazar; modülde hiç metin yoksa dosya oluşturulmaz.
     *
     * @param  array<string, string>  $catalog
     */
    public function write(array $catalog): void
    {
        if ($catalog === [] && ! is_file($this->file())) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->file()));
        File::put($this->file(), self::encode($catalog));
    }

    /**
     * Diğer dillerde eksik anahtar sayısı ({lang}/{locale}.json), dil => sayı.
     *
     * @param  array<string, string>  $catalog
     * @return array<string, int>
     */
    public function missing(array $catalog): array
    {
        $result = [];

        foreach (File::glob($this->root.'/lang/*.json') as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);

            if ($code !== 'tr') {
                $result[$code] = count(array_diff_key($catalog, $this->read($code)));
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $catalog
     */
    public static function encode(array $catalog): string
    {
        $json = json_encode($catalog === [] ? new \stdClass : $catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json."\n";
    }

    /**
     * @return array<string, string>
     */
    private function read(string $code): array
    {
        $path = $this->file($code);

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> $data */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }

    private function excluded(string $relative): bool
    {
        foreach (self::EXCLUDE as $prefix) {
            if (str_starts_with($relative, $prefix.'/') || str_contains($relative, '/'.$prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function unescape(string $raw, string $quote): string
    {
        if ($quote === "'") {
            return strtr($raw, ["\\'" => "'", '\\\\' => '\\']);
        }

        return strtr($raw, ['\\'.$quote => $quote, '\\\\' => '\\', '\\n' => "\n", '\\t' => "\t", '\\$' => '$']);
    }
}
