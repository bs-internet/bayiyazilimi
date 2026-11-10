<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Shop\ProductImporter;
use Illuminate\Console\Command;

/**
 * Ürünleri ürün Excel şablonundan (ProductSheet; panel › İçe / Dışa Aktar › Boş şablon) içe aktarır.
 *
 * Örnek: php artisan shop:import-products urunler.xlsx --images=gorseller --download-images
 */
final class ImportProductsCommand extends Command
{
    protected $signature = 'shop:import-products
        {file : Excel dosyasının yolu}
        {--images= : Ürün görselleri klasörü (klasör/{Stok Kodu}/*.jpg)}
        {--download-images : Görseli olmayan ürünlerin görsellerini "Görsel 1..5" bağlantılarından indir}
        {--no-update : Var olan ürünleri güncelleme}
        {--no-stock : Var olan ürünlerin stoklarını güncelleme}
        {--limit= : Yalnızca ilk N satır (deneme)}';

    protected $description = 'Excel ürün listesini içe aktarır (ürün, marka, kategori, fiyat, depo stokları, görseller).';

    public function handle(ProductImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("Dosya bulunamadı: {$file}");

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% satır [%bar%] %elapsed:6s% %message%');
        $bar->setMessage('');
        $bar->start();

        $result = $importer
            ->onProgress(function (int $index, string $sku) use ($bar): void {
                $bar->setMessage($sku);
                $bar->setProgress($index);
            })
            ->import($file, [
                'images_dir' => $this->option('images') ?: null,
                'download_images' => (bool) $this->option('download-images'),
                'update_existing' => ! $this->option('no-update'),
                'update_stock' => ! $this->option('no-stock'),
                'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
            ]);

        $bar->finish();
        $this->newLine(2);

        $this->table(['Yeni', 'Güncellenen', 'Atlanan', 'Görsel', 'Hata'], [[
            $result['created'], $result['updated'], $result['skipped'], $result['images'], count($result['errors']),
        ]]);

        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
