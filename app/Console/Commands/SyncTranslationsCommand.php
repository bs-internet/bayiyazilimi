<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Translations\TranslationCatalog;
use Illuminate\Console\Command;

/**
 * Çeviri kataloglarını kaynaktaki `__()` / `t()` metinleriyle eşitler:
 * çekirdek lang/tr.json + kurulu her modülün lang/tr.json dosyası.
 * `--check`: yazmaz; bir katalog güncel değilse hata verir (test / CI).
 */
final class SyncTranslationsCommand extends Command
{
    protected $signature = 'lang:sync {--check : Yazmadan denetle; güncel değilse başarısız ol}';

    protected $description = 'Çeviri kataloglarını (çekirdek ve kurulu modüller) kaynaktaki metinlerle eşitler.';

    public function handle(): int
    {
        $stale = false;

        foreach (TranslationCatalog::all(base_path()) as $catalog) {
            [$next, $added, $removed] = $catalog->plan();
            $file = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $catalog->file());

            if ($this->option('check')) {
                if ($added !== [] || $removed !== []) {
                    $stale = true;
                    $this->error("{$file} güncel değil.");
                    $this->listKeys('Eklenecek', $added);
                    $this->listKeys('Çıkarılacak', $removed);
                }

                continue;
            }

            $catalog->write($next);
            $this->info("{$file}: ".count($next).' metin · '.count($added).' eklendi · '.count($removed).' çıkarıldı.');

            foreach ($catalog->missing($next) as $code => $count) {
                if ($count > 0) {
                    $this->warn("  {$code}: {$count} metnin çevirisi eksik.");
                }
            }
        }

        if ($stale) {
            $this->error('`php artisan lang:sync` çalıştırın.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('Çeviri katalogları güncel.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     */
    private function listKeys(string $title, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $this->line("  {$title} (".count($keys).'):');

        foreach (array_slice($keys, 0, 30) as $key) {
            $this->line('    · '.$key);
        }

        if (count($keys) > 30) {
            $this->line('    … '.(count($keys) - 30).' metin daha');
        }
    }
}
