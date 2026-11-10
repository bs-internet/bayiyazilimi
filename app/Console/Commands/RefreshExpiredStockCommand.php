<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Süresi yeni dolan lotları satılabilir stoktan düşer: stok hareketi olmadan
 * değişen tek durum budur, bu yüzden her gece çalışır (routes/console.php).
 */
final class RefreshExpiredStockCommand extends Command
{
    protected $signature = 'shop:refresh-expired-stock {--days=2 : Son kaç günde süresi dolan lotlar}';

    protected $description = 'Süresi yeni dolan lotların ürünlerinde satılabilir stoğu yeniden hesaplar.';

    /** Son başarılı çalışmanın günü: zamanlayıcı birkaç gün çalışmazsa aradaki günler de taranır. */
    public const LAST_RUN_KEY = 'shop.expired_stock_refreshed_on';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $since = $today->subDays(max(1, (int) $this->option('days')));
        $lastRun = Cache::get(self::LAST_RUN_KEY);

        if (is_string($lastRun) && CarbonImmutable::parse($lastRun)->isBefore($since)) {
            $since = CarbonImmutable::parse($lastRun)->subDay();
        }

        $count = 0;

        Product::query()
            ->whereHas('lots', fn (Builder $q) => $q
                ->whereDate('expires_at', '<', $today)
                ->whereDate('expires_at', '>=', $since)
                ->whereHas('stocks', fn (Builder $s) => $s->where('quantity', '>', 0)))
            ->each(function (Product $product) use (&$count) {
                $product->syncStockTotal();
                $count++;
            });

        Cache::forever(self::LAST_RUN_KEY, $today->toDateString());

        $this->info(__(':count ürünün satılabilir stoğu güncellendi.', ['count' => $count]));

        return self::SUCCESS;
    }
}
