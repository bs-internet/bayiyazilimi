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

// Günlük veritabanı yedeği (storage/app/private/backups, en yeni BAYIYAZILIMI_BACKUP_KEEP adet).
Schedule::command('bayi:backup')->dailyAt((string) config('bayiyazilimi.backup.at', '03:30'))->withoutOverlapping(60);

// Paylaşımlı hosting: kuyruk ayrı bir süreç yöneticisi olmadan da işlensin. Zamanlayıcı her
// dakika kuyruğu boşaltır; en sonda tanımlıdır ki diğer görevleri bekletmesin. Kilit 2 dakikada
// düşer (yarıda kesilen işleyici kuyruğu bir gün kilitli bırakmasın).
if (config('bayiyazilimi.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);
}
