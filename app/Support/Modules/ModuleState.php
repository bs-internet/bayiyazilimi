<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * Panelden kapatılan modüllerin kaydı (JSON dosyası, `bayiyazilimi.module_state`).
 *
 * Biçim: `{"disabled": {"<modül anahtarı>": "<provider sınıfı>"}}`. Provider
 * sınıfı da saklanır çünkü dosya, uygulama açılırken (provider'lar ve
 * veritabanı hazır olmadan) {@see ModulePackageManifest} tarafından okunur;
 * o anda module.json dosyalarını taramak gerekmesin.
 */
final class ModuleState
{
    /** @var array<string, string>|null */
    private ?array $disabled = null;

    public function __construct(private readonly string $path) {}

    public function isEnabled(string $key): bool
    {
        return ! array_key_exists($key, $this->disabled());
    }

    /**
     * Kapalı modüller: anahtar => provider sınıfı.
     *
     * @return array<string, string>
     */
    public function disabled(): array
    {
        return $this->disabled ??= $this->read();
    }

    public function disable(ModuleManifest $manifest): void
    {
        $this->update(fn (array $disabled): array => [...$disabled, $manifest->key => $manifest->provider]);
    }

    public function enable(string $key): void
    {
        $this->update(function (array $disabled) use ($key): array {
            unset($disabled[$key]);

            return $disabled;
        });
    }

    /**
     * Oku-değiştir-yaz tek kilit altında: eşzamanlı iki açma / kapama birbirinin
     * değişikliğini ezmez. Geri çağrı güncel listeyi alır (bağımlılık denetimi
     * burada yapılırsa o da kilit altındadır) ve yeni listeyi döner; hata
     * fırlatırsa dosya değişmez.
     *
     * @param  callable(array<string, string>): array<string, string>  $change
     */
    public function update(callable $change): void
    {
        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0755, true);
        }

        $lock = fopen($this->path.'.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException(__('Eklenti durum dosyası kilitlenemedi.'));
        }

        try {
            $disabled = $change($this->read());
            ksort($disabled);

            // Önce geçici dosyaya yazılıp yerine taşınır: kilitsiz okuyan (uygulama
            // açılışı) yarım yazılmış dosya görmez.
            $tmp = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';
            file_put_contents($tmp, json_encode(['disabled' => (object) $disabled], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

            if (! rename($tmp, $this->path)) {
                @unlink($tmp);

                throw new \RuntimeException(__('Eklenti durum dosyası yazılamadı.'));
            }

            $this->disabled = $disabled;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, string>
     */
    private function read(): array
    {
        $data = is_file($this->path) ? json_decode((string) file_get_contents($this->path), true) : null;
        $disabled = is_array($data) && is_array($data['disabled'] ?? null) ? $data['disabled'] : [];

        return array_filter(
            $disabled,
            fn (mixed $provider, mixed $key): bool => is_string($key) && is_string($provider) && $provider !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
