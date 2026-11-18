<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Events\Shop\DueDigestReadyEvent;
use App\Events\Shop\DueReminderDueEvent;
use App\Models\Account;
use App\Models\DueReminder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Vade hatırlatmaları (günlük görev). Cari Ayarları'nda her tür için bayi ve
 * yönetici ayrı açılır, hepsi varsayılan kapalı:
 *
 * |                     | Bayi                              | Yönetici                       |
 * |---------------------|-----------------------------------|--------------------------------|
 * | Vadesi yaklaşan     | `due_reminder_upcoming_enabled`   | `due_reminder_upcoming_admin`  |
 * | Vadesi geçen        | `due_reminder_overdue_enabled`    | `due_reminder_overdue_admin`   |
 *
 * Bayi: kendi borçları, Account::ledgerEmails() adreslerine. Yaklaşan borç
 * (vadesine `due_warning_days` gün kalan) için bir kez; geçen borç için bir kez,
 * sonra `overdue_reminder_repeat_days` günde bir (0 = yalnız bir kez). Adresi
 * olmayan hesaba gönderilmez (kayıt da yazılmaz, adres eklenince gönderilir).
 *
 * Yönetici: her sabah tek bir toplu e-posta, bayi bazında o anki durum
 * (yineleme kaydı tutulmaz; liste boşsa gönderilmez). Alıcılar cari yetkisi
 * olan aktif yöneticilerdir (SendDueDigest).
 */
final class DueReminderService
{
    public function __construct(
        private readonly CreditService $credit,
    ) {}

    /**
     * @return array{dealers: int, digest: bool} hatırlatma gönderilen bayi sayısı, yönetici özeti gitti mi
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $dealer = ['upcoming' => ShopSettings::bool('due_reminder_upcoming_enabled'), 'overdue' => ShopSettings::bool('due_reminder_overdue_enabled')];
        $admin = ['upcoming' => ShopSettings::bool('due_reminder_upcoming_admin'), 'overdue' => ShopSettings::bool('due_reminder_overdue_admin')];

        if (! in_array(true, [...array_values($dealer), ...array_values($admin)], true)) {
            return ['dealers' => 0, 'digest' => false];
        }

        $now ??= CarbonImmutable::now();
        $warningDays = ShopSettings::int('due_warning_days');
        $repeatDays = ShopSettings::int('overdue_reminder_repeat_days');
        $sent = 0;
        $digest = [];

        Account::query()
            ->whereHas('transactions', fn (Builder $q) => $q->whereNotNull('due_date'))
            ->lazyById(100)
            ->each(function (Account $account) use ($now, $warningDays, $repeatDays, $dealer, $admin, &$sent, &$digest): void {
                $dues = $this->credit->dues($account, $warningDays);

                if ($dues['overdue'] === [] && $dues['upcoming'] === []) {
                    return;
                }

                if ($this->remind($account, $this->only($dues, $dealer), $now, $repeatDays)) {
                    $sent++;
                }

                $row = $this->digestRow($account, $this->only($dues, $admin));

                if ($row !== null) {
                    $digest[] = $row;
                }
            });

        if ($digest !== []) {
            usort($digest, fn (array $a, array $b) => [$b['overdue_days'], $a['account']] <=> [$a['overdue_days'], $b['account']]);
            DueDigestReadyEvent::dispatch($digest, $admin['overdue'], $admin['upcoming']);
        }

        return ['dealers' => $sent, 'digest' => $digest !== []];
    }

    /**
     * @param  array{overdue: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}  $dues
     * @param  array{upcoming: bool, overdue: bool}  $enabled
     * @return array{overdue: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}
     */
    private function only(array $dues, array $enabled): array
    {
        return [
            'overdue' => $enabled['overdue'] ? $dues['overdue'] : [],
            'upcoming' => $enabled['upcoming'] ? $dues['upcoming'] : [],
        ];
    }

    /**
     * @param  array{overdue: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}  $dues
     */
    private function remind(Account $account, array $dues, CarbonImmutable $now, int $repeatDays): bool
    {
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

    /**
     * Yönetici özetinin bir bayi satırı (adet, kalan toplam, en eski gecikme).
     *
     * @param  array{overdue: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}  $dues
     * @return array<string, mixed>|null
     */
    private function digestRow(Account $account, array $dues): ?array
    {
        if ($dues['overdue'] === [] && $dues['upcoming'] === []) {
            return null;
        }

        $sum = fn (array $items) => Money::sum(array_map(fn (array $d) => $d['open_amount'], $items));

        return [
            'account' => (string) ($account->title ?? $account->code),
            'code' => (string) $account->code,
            'overdue_count' => count($dues['overdue']),
            'overdue_total' => $dues['overdue'] !== [] ? Money::format($sum($dues['overdue'])) : null,
            'overdue_days' => $dues['overdue'] !== [] ? max(array_column($dues['overdue'], 'days')) : 0,
            'upcoming_count' => count($dues['upcoming']),
            'upcoming_total' => $dues['upcoming'] !== [] ? Money::format($sum($dues['upcoming'])) : null,
        ];
    }
}
