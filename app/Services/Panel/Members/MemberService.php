<?php

declare(strict_types=1);

namespace App\Services\Panel\Members;

use App\Enums\Shop\CustomerType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Events\Panel\Members\MemberCreatedEvent;
use App\Events\Panel\Members\MemberEmailChangeConfirmedEvent;
use App\Events\Panel\Members\MemberEmailChangeRequestedEvent;
use App\Events\Panel\Members\MemberEmailVerifiedEvent;
use App\Events\Panel\Members\MemberStatusUpdatedEvent;
use App\Events\Panel\Members\MemberWelcomeAcceptedEvent;
use App\Models\Activity;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Quote;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Account\AccountProvisioner;
use App\Services\Panel\Shop\DashboardService;
use App\Support\Money;
use App\Support\Pagination;
use Brick\Math\BigNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Yadahan\AuthenticationLog\AuthenticationLog;

class MemberService
{
    public function __construct(
        private readonly AccountProvisioner $provisioner,
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * @param  array{q?: string, type?: string, group?: string, status?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['q'] ?? '';
        $escaped = addcslashes($search, '%_\\');
        $type = CustomerType::tryFrom($filters['type'] ?? '');
        $group = $filters['group'] ?? '';
        $status = UserStatus::tryFrom($filters['status'] ?? '');

        return User::query()
            ->select('users.*')
            ->addSelect($this->orderStatsSelects())
            ->with('account.dealerGroup')
            ->where('user_type', UserType::Member->value)
            // Hesabı henüz açılmamış (e-postasını doğrulamamış) üye müşteri sayılır.
            ->when($type === CustomerType::Dealer, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', CustomerType::Dealer->value)))
            ->when($type === CustomerType::Customer, fn ($q) => $q->whereDoesntHave('account', fn ($a) => $a->where('type', CustomerType::Dealer->value)))
            ->when($group === 'none', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', CustomerType::Dealer->value)->whereNull('dealer_group_id')))
            ->when($group !== '' && $group !== 'none', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('dealer_group_id', $group)))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($search !== '', function ($query) use ($escaped) {
                $query->where(function ($q) use ($escaped) {
                    $q->where('name', 'like', '%'.$escaped.'%')
                        ->orWhere('email', 'like', '%'.$escaped.'%')
                        ->orWhereHas('account', fn ($a) => $a->where('title', 'like', '%'.$escaped.'%'));
                });
            })
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Müşteri detayı: sipariş özeti, son siparişler / ödemeler / teklifler /
     * iadeler ve kayıtlı adresler. Hesabı olmayan (e-postasını doğrulamamış)
     * üyede tüm listeler boş döner.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, int $limit = 20): array
    {
        $accountId = $user->account_id;

        if ($accountId === null) {
            return [
                'stats' => [
                    'orders_count' => 0,
                    'total_spent' => $this->money(0),
                    'average_order' => null,
                    'last_order_at' => null,
                ],
                'orders' => ['data' => [], 'total' => 0],

                'quotes' => ['data' => [], 'total' => 0],
                'returns' => ['data' => [], 'total' => 0],
                'addresses' => [],
            ];
        }

        $stats = $this->dashboard->revenueQuery()
            ->where('account_id', $accountId)
            ->selectRaw('count(*) as cnt, coalesce(sum('.$this->dashboard->tlExpression('grand_total').'), 0) as total')
            ->toBase()
            ->first();

        $count = (int) ($stats->cnt ?? 0);
        $total = Money::round($stats->total ?? 0);

        $orders = Order::query()
            ->where('account_id', $accountId)
            ->withCount('items')
            ->latest()
            ->limit($limit)
            ->get();

        $quotes = Quote::query()
            ->where('account_id', $accountId)
            ->withCount('items')
            ->latest()
            ->limit($limit)
            ->get();

        $returns = ReturnRequest::query()
            ->where('account_id', $accountId)
            ->with('order:id,number,currency_code')
            ->withCount('items')
            ->latest()
            ->limit($limit)
            ->get();

        $addresses = CustomerAddress::query()
            ->where('account_id', $accountId)
            ->with(['country:id,name', 'city:id,name', 'district:id,name'])
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->orderBy('title')
            ->get();

        return [
            'stats' => [
                'orders_count' => $count,
                'total_spent' => $this->money($total),
                'average_order' => $count > 0 ? $this->money(Money::div($total, $count)) : null,
                'last_order_at' => $orders->first()?->created_at?->toIso8601String(),
            ],
            'orders' => [
                'total' => Order::query()->where('account_id', $accountId)->count(),
                'data' => $orders->map(fn (Order $o): array => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'payment_status' => $o->payment_status->value,
                    'payment_status_label' => $o->payment_status->label(),
                    'items_count' => $o->items_count,
                    'grand_total' => $this->money($o->grand_total, $o->currency_code),
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values()->all(),
            ],

            'quotes' => [
                'total' => Quote::query()->where('account_id', $accountId)->count(),
                'data' => $quotes->map(fn (Quote $q): array => [
                    'id' => $q->id,
                    'number' => $q->number,
                    'status' => $q->status->value,
                    'status_label' => $q->status->label(),
                    'items_count' => $q->items_count,
                    'grand_total' => $q->grand_total->isPositive() ? $this->money($q->grand_total, $q->currency_code) : null,
                    'created_at' => $q->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'returns' => [
                'total' => ReturnRequest::query()->where('account_id', $accountId)->count(),
                'data' => $returns->map(fn (ReturnRequest $r): array => [
                    'id' => $r->id,
                    'number' => $r->number,
                    'order_id' => $r->order_id,
                    'order_number' => $r->order?->number,
                    'status' => $r->status->value,
                    'status_label' => $r->status->label(),
                    'reason_label' => $r->reason->label(),
                    'items_count' => $r->items_count,
                    'refund_amount' => $r->refund_amount !== null
                        ? $this->money($r->refund_amount, $r->order->currency_code ?? null)
                        : null,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'addresses' => $addresses->map(fn (CustomerAddress $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type->value,
                'name' => $a->name,
                'company' => $a->company,
                'tax_office' => $a->tax_office,
                'tax_number' => $a->tax_number,
                'identity_number' => $a->identity_number,
                'phone' => $a->phone,
                'address' => $a->address,
                'postal_code' => $a->postal_code,
                'district' => $a->district?->name,
                'city' => $a->city?->name,
                'country' => $a->country?->name,
                'is_default_shipping' => $a->is_default_shipping,
                'is_default_billing' => $a->is_default_billing,
            ])->values()->all(),
        ];
    }

    /**
     * Liste satırları için hesap bazlı sipariş sayısı ve TL toplam harcama
     * (ciro tanımı DashboardService ile aynıdır).
     *
     * @return array<string, Builder<Order>>
     */
    private function orderStatsSelects(): array
    {
        return [
            'orders_count' => $this->dashboard->revenueQuery()
                ->selectRaw('count(*)')
                ->whereColumn('orders.account_id', 'users.account_id'),
            'orders_total' => $this->dashboard->revenueQuery()
                ->selectRaw('coalesce(sum('.$this->dashboard->tlExpression('grand_total').'), 0)')
                ->whereColumn('orders.account_id', 'users.account_id'),
        ];
    }

    private function money(BigNumber|int|string|null $amount, ?string $code = null): string
    {
        return Money::formatIn($amount, $code);
    }

    /**
     * @return EloquentCollection<int, Activity>
     */
    public function activities(User $user): EloquentCollection
    {
        return Activity::query()
            ->with('causer')
            ->where(function ($query) use ($user) {
                $query->where('causer_id', $user->getKey())
                    ->orWhere(function ($inner) use ($user) {
                        $inner->where('subject_type', User::class)
                            ->where('subject_id', $user->getKey());
                    })
                    ->orWhere('properties->user_id', $user->getKey());
            })
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * @return EloquentCollection<int, AuthenticationLog>
     */
    public function authentications(User $user): EloquentCollection
    {
        return $user->authentications()
            ->limit(20)
            ->get();
    }

    /**
     * Panelden eklenen üye her zaman bayidir (firma ekler; müşteriler vitrinden kayıt olur).
     *
     * @param  array{name: string, email: string, status: string, email_verified?: bool, company?: ?string, dealer_group_id?: ?string, discount_rate?: float|string|null, warehouse_id?: ?string}  $data
     */
    public function create(array $data, User $causer): User
    {
        return DB::transaction(function () use ($data, $causer): User {
            $emailVerified = (bool) ($data['email_verified'] ?? false);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(64)),
                'status' => UserStatus::from($data['status']),
                'user_type' => UserType::Member,
                'locale' => $causer->locale ?? config('app.locale'),
                'timezone' => $causer->timezone ?? config('app.timezone'),
            ]);

            if ($emailVerified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            MemberCreatedEvent::dispatch($user, $causer, $emailVerified);

            $rate = $data['discount_rate'] ?? null;

            $this->provisioner->ensureForUser($user)?->fill([
                'title' => ($data['company'] ?? null) ?: null,
                'type' => CustomerType::Dealer,
                'dealer_group_id' => $data['dealer_group_id'] ?? null,
                'warehouse_id' => ($data['warehouse_id'] ?? null) ?: null,
                'discount_rate' => $rate !== null && $rate !== '' ? Money::round((string) $rate) : null,
            ])->save();

            return $user;
        });
    }

    public function verifyEmail(User $user, User $causer): User
    {
        return DB::transaction(function () use ($user, $causer): User {
            if ($user->email_verified_at !== null) {
                return $user;
            }

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            MemberEmailVerifiedEvent::dispatch($user, $causer);

            return $user;
        });
    }

    public function requestEmailChange(User $user, User $causer, string $email): void
    {
        DB::transaction(function () use ($user, $causer, $email): void {
            $this->logoutUserFromAllSessions($user);

            MemberEmailChangeRequestedEvent::dispatch($user, $causer, $email);
        });
    }

    public function confirmEmailChange(User $user, string $email): User
    {
        return DB::transaction(function () use ($user, $email): User {
            $oldEmail = (string) $user->email;

            $user->forceFill([
                'email' => $email,
                'email_verified_at' => now(),
            ])->save();

            $this->logoutUserFromAllSessions($user);

            MemberEmailChangeConfirmedEvent::dispatch($user, $oldEmail, $email);

            return $user;
        });
    }

    public function updateStatus(User $user, User $causer, UserStatus $status): User
    {
        return DB::transaction(function () use ($user, $causer, $status): User {
            $oldStatus = $user->status;

            if ($oldStatus === $status) {
                return $user;
            }

            $user->forceFill([
                'status' => $status,
            ])->save();

            // Girişi kapanan üyenin açık oturumları hemen sonlanır.
            if (! $status->canLogin()) {
                $this->logoutUserFromAllSessions($user);
            }

            MemberStatusUpdatedEvent::dispatch($user, $causer, $oldStatus, $status);

            return $user;
        });
    }

    public function acceptWelcome(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $emailVerified = false;

            if ($user->email_verified_at === null) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();

                $emailVerified = true;
            }

            MemberWelcomeAcceptedEvent::dispatch($user, $emailVerified);

            return $user;
        });
    }

    private function logoutUserFromAllSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->authentications()
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);
    }
}
