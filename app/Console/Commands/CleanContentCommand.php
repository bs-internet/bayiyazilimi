<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use Illuminate\Console\Command;

/**
 * Kayıtlı ürün açıklamalarını ve sözleşme metinlerini HtmlSanitizer ile sadeleştirir
 * (eski siteden gelen inline stil, sabit genişlik, <pre>, iç içe div/span…).
 */
final class CleanContentCommand extends Command
{
    protected $signature = 'shop:clean-content {--dry-run : Yalnızca kaç kaydın değişeceğini göster}';

    protected $description = 'Ürün açıklamalarını ve sözleşme metinlerini temizler.';

    public function handle(): int
    {
        $changed = 0;

        Product::withTrashed()->whereNotNull('description')->select(['id', 'description'])->chunkById(200, function ($products) use (&$changed) {
            foreach ($products as $product) {
                $clean = HtmlSanitizer::clean($product->description);

                if ($clean !== $product->description) {
                    $changed++;

                    if (! $this->option('dry-run')) {
                        $product->forceFill(['description' => $clean === '' ? null : $clean])->saveQuietly();
                    }
                }
            }
        });

        foreach (Contract::query()->get(['id', 'content']) as $contract) {
            $clean = HtmlSanitizer::clean($contract->content);

            if ($clean !== $contract->content) {
                $changed++;

                if (! $this->option('dry-run')) {
                    $contract->forceFill(['content' => $clean])->saveQuietly();
                }
            }
        }

        $this->info(($this->option('dry-run') ? 'Değişecek' : 'Temizlenen').' kayıt: '.$changed);

        return self::SUCCESS;
    }
}
