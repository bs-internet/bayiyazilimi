<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TCMB kurları hafta içi 15:30'da açıklanır; günde birkaç kez kontrol edilir.
Schedule::command('shop:sync-rates')->weekdays()->at('09:05');
Schedule::command('shop:sync-rates')->weekdays()->at('15:45');

// Yarım kalan (zaman aşımına uğrayan) kart ödemeleri başarısız sayılır, stok geri eklenir.
Schedule::command('shop:expire-pending-payments')->everyFiveMinutes()->withoutOverlapping();

// Stok rezervasyon süresi dolan (ödemesi gelmeyen havale / onaylanmayan vadeli) siparişler kapanır.
Schedule::command('shop:release-stock-reservations')->hourly()->withoutOverlapping();

// Süresi dolan lotlar satılabilir stoktan düşülür (stok hareketi olmadan değişen tek durum).
Schedule::command('shop:refresh-expired-stock')->dailyAt('00:10');
