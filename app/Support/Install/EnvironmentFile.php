<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/**
 * `.env` dosyasında anahtar yazar / günceller; diğer satırlara dokunmaz.
 * Dosya yoksa `.env.example`'dan oluşturulur.
 */
final class EnvironmentFile
{
    public function __construct(
        private readonly string $path,
        private readonly ?string $examplePath = null,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function ensureExists(): void
    {
        if (is_file($this->path)) {
            return;
        }

        $contents = $this->examplePath !== null && is_file($this->examplePath)
            ? (string) file_get_contents($this->examplePath)
            : '';

        $this->write($contents);
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $this->ensureExists();
        $contents = (string) file_get_contents($this->path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $replacement = str_replace(['\\', '$'], ['\\\\', '\\$'], $line);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            // .env.example'daki yorum satırı ("# DB_HOST=127.0.0.1") yerinde açılır; anahtar en alta eklenmez.
            $commented = '/^#[ \t]*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $replacement, $contents);
            } elseif (preg_match($commented, $contents) === 1) {
                $contents = (string) preg_replace($commented, $replacement, $contents, 1);
            } else {
                $contents = rtrim($contents, "\n")."\n".$line."\n";
            }
        }

        $this->write($contents);
    }

    private function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-=]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function write(string $contents): void
    {
        if (@file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException(__('.env dosyası yazılamadı: :path', ['path' => $this->path]));
        }
    }
}
