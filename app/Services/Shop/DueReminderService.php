<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Events\Shop\DueReminderDueEvent;
use App\Models\Account;
use App\Models\DueReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Vade hatırlatması (Cari Ayarları › `due_reminders_enabled`, günlük görev):
 * vadesi yaklaşan (`due_warning_days`) ya da geçen açık borcu olan bayiye
 * e-posta gider. Aynı borç için:
 *
 * - "yaklaşıyor" hatırlatması bir kez,
 * - "vadesi geçti" hatırlatması bir kez, sonra `overdue_reminder_repeat_days`
 *   günde bir (0 = yalnız bir kez)
 *
 * gönderilir. E-posta o anki tüm geçen / yaklaşan borçları listeler; adresi
 * olmayan hesaba gönderilmez (hatırlatma kaydı da yazılmaz).
 */
final class DueReminderService
{
    public function __construct(
        private readonly CreditService $credit,
    ) {}

    /**
     * @return int hatırlatma gönderilen hesap sayısı
     */
    public function run(?CarbonImmutable $now = null): int
    {
        if (! ShopSettings::bool('due_reminders_enabled')) {
            return 0;
        }

        $now ??= CarbonImmutable::now();
        $warningDays = ShopSettings::int('due_warning_days');
        $repeatDays = ShopSettings::int('overdue_reminder_repeat_days');
        $sent = 0;

        Account::query()
            ->whereHas('transactions', fn (Builder $q) => $q->whereNotNull('due_date'))
            ->lazyById(100)
            ->each(function (Account $account) use ($now, $warningDays, $repeatDays, &$sent): void {
                if ($this->remind($account, $now, $warningDays, $repeatDays)) {
                    $sent++;
                }
            });

        return $sent;
    }

    private function remind(Account $account, CarbonImmutable $now, int $warningDays, int $repeatDays): bool
    {
        $dues = $this->credit->dues($account, $warningDays);

        if ($dues['overdue'] === [] && $dues['upcoming'] === []) {
            return false;
        }

        $ids = array_column([...$dues['overdue'], ...$dues['upcoming']], 'id');
        $last = DueReminder::query()->whereIn('account_transaction_id', $ids)->get()
            ->mapWithKeys(fn (DueReminder $r) => [$r->account_transaction_id.'|'.$r->stage => $r->sent_at]);

        $due = collect($dues['upcoming'])->contains(fn (array $d) => ! $last->has($d['id'].'|'.DueReminder::UPCOMING))
            || collect($dues['overdue'])->contains(function (array $d) use ($last, $now, $repeatDays): bool {
                $at = $last->get($d['id'].'|'.DueReminder::OVERDUE);

                return $at === null || ($repeatDays > 0 && $at->lte($now->subDays($repeatDays)->addMinutes(5)));
            });

        if (! $due) {
            return false;
        }

        $emails = $account->ledgerEmails();

        if ($emails === []) {
            return false;
        }

        DB::transaction(function () use ($dues, $now): void {
            foreach ([DueReminder::OVERDUE => $dues['overdue'], DueReminder::UPCOMING => $dues['upcoming']] as $stage => $items) {
                foreach ($items as $d) {
                    DueReminder::query()->updateOrCreate(
                        ['account_transaction_id' => $d['id'], 'stage' => $stage],
                        ['sent_at' => $now],
                    );
                }
            }
        });

        DueReminderDueEvent::dispatch($account, $emails, $dues['overdue'], $dues['upcoming']);

        return true;
    }
}
