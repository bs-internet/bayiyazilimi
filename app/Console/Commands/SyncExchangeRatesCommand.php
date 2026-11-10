<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Shop\ShopException;
use App\Services\Shop\ExchangeRateService;
use App\Services\Shop\ShopSettings;
use Illuminate\Console\Command;

/**
 * Kaynağı "TCMB" olan para birimlerinin kurlarını TCMB'den günceller.
 * Zamanlayıcı (routes/console.php) hafta içi günde iki kez çalıştırır.
 */
final class SyncExchangeRatesCommand extends Command
{
    protected $signature = 'shop:sync-rates {--force : Otomatik güncelleme kapalı olsa da çalıştır}';

    protected $description = 'Döviz kurlarını TCMB\'den günceller.';

    public function handle(ExchangeRateService $service): int
    {
        if (! $this->option('force') && ! ShopSettings::bool('tcmb_auto_update')) {
            $this->info('TCMB otomatik güncelleme kapalı (Mağaza Ayarları).');

            return self::SUCCESS;
        }

        try {
            $updated = $service->syncFromTcmb();
        } catch (ShopException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($updated === []) {
            $this->info('Güncellenecek (kaynağı TCMB olan) para birimi yok.');

            return self::SUCCESS;
        }

        foreach ($updated as $code => $rate) {
            $this->line("{$code}: {$rate}");
        }

        return self::SUCCESS;
    }
}
