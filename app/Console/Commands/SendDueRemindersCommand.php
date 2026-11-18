<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Shop\DueReminderService;
use Illuminate\Console\Command;

/**
 * Vadesi yaklaşan / geçen borç için bayilere e-posta hatırlatması
 * (Cari Ayarları'nda açıkken). Her gün çalışır (routes/console.php).
 */
final class SendDueRemindersCommand extends Command
{
    protected $signature = 'shop:send-due-reminders';

    protected $description = 'Vadesi yaklaşan ve geçen cari borçlar için bayilere e-posta hatırlatması gönderir.';

    public function handle(DueReminderService $reminders): int
    {
        $result = $reminders->run();

        $this->info(__(':count bayiye vade hatırlatması gönderildi.', ['count' => $result['dealers']]));

        if ($result['digest']) {
            $this->info(__('Yöneticilere günlük vade özeti gönderildi.'));
        }

        return self::SUCCESS;
    }
}
