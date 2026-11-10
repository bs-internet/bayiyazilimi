<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\ReturnStatus;
use App\Enums\UserType;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\ActivitySubjectLabels;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Panel başlangıç ekranı verileri (e-ticaret özeti).
 *
 * Ciro tanımı: iptal edilen / ödemesi başarısız olan siparişler ve ödemesi
 * iade edilen siparişler hariç tüm siparişlerin `grand_total` toplamı; TL
 * dışındaki siparişler sipariş anındaki kurla (`exchange_rate`, 1 birim = X TL)
 * ana para birimine çevrilir.
 */
class DashboardService
{
    public const PERIOD_DAYS = 30;

    /**
     * @return array{revenue: array<string, mixed>, orders: array<string, mixed>, average_order: array<string, mixed>}
     */
    public function orderKpis(): array
    {
        [$from, $prevFrom] = $this->periods();

        $current = $this->revenueQuery()->where('created_at', '>=', $from)
            ->selectRaw('count(*) as cnt, coalesce(sum('.$this->tlExpression('grand_total').'), 0) as total')
            ->first();
        $previous = $this->revenueQuery()->where('created_at', '>=', $prevFrom)->where('created_at', '<', $from)
            ->selectRaw('count(*) as cnt, coalesce(sum('.$this->tlExpression('grand_total').'), 0) as total')
            ->first();

        $revenue = Money::round($current->total);
        $prevRevenue = Money::round($previous->total);
        $count = (int) $current->cnt;
        $prevCount = (int) $previous->cnt;
        $average = Money::div($revenue, $count);
        $prevAverage = Money::div($prevRevenue, $prevCount);

        return [
            'revenue' => [
                'value' => $revenue,
                'formatted' => $this->money($revenue),
                'change' => $this->change($revenue, $prevRevenue),
            ],
            'orders' => [
                'value' => $count,
                'change' => $this->change($count, $prevCount),
            ],
            'average_order' => [
                'value' => $average,
                'formatted' => $this->money($average),
                'change' => $this->change($average, $prevAverage),
            ],
        ];
    }

    /**
     * Son 30 günde kayıt olan üyeler (müşteri / bayi kırılımıyla).
     *
     * @return array{total: int, customers: int|null, dealers: int, change: float|null}
     */
    public function newMembers(): array
    {
        [$from, $prevFrom] = $this->periods();

        $members = fn (): Builder => User::query()->where('user_type', UserType::Member->value);

        $total = $members()->where('created_at', '>=', $from)->count();
        $dealers = $members()->where('created_at', '>=', $from)
            ->whereHas('account', fn (Builder $q) => $q->where('type', CustomerType::Dealer->value))
            ->count();
        $previous = $members()->where('created_at', '>=', $prevFrom)->where('created_at', '<', $from)->count();

        return [
            'total' => $total,
            // Müşteri kırılımı yalnız müşteri hesapları varken (vitrin); yoksa null.
            'customers' => CustomerType::customersEnabled() ? $total - $dealers : null,
            'dealers' => $dealers,
            'change' => $this->change($total, $previous),
        ];
    }

    /**
     * Son 30 günün günlük cirosu (boş günler 0).
     *
     * @return array<int, array{date: string, revenue: BigDecimal, orders: int}>
     */
    public function dailyRevenue(): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subDays(self::PERIOD_DAYS - 1);

        $rows = $this->revenueQuery()
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as cnt, sum('.$this->tlExpression('grand_total').') as total')
            ->groupByRaw('date(created_at)')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $days = [];
        for ($d = $from; $d->lte($today); $d = $d->addDay()) {
            $key = $d->toDateString();
            $row = $rows->get($key);
            $days[] = [
                'date' => $key,
                'revenue' => Money::round($row->total ?? 0),
                'orders' => (int) ($row->cnt ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Tüm siparişlerin duruma göre dağılımı (son 30 gün).
     *
     * @return array<int, array{status: string, label: string, count: int}>
     */
    public function ordersByStatus(): array
    {
        [$from] = $this->periods();

        $counts = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return array_map(fn (OrderStatus $s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'count' => (int) ($counts[$s->value] ?? 0),
        ], OrderStatus::cases());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentOrders(int $limit = 8): array
    {
        return Order::query()
            ->latest()
            ->limit($limit)
            ->get(['id', 'number', 'customer_name', 'email', 'status', 'payment_status', 'currency_code', 'grand_total', 'created_at'])
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer_name' => $o->customer_name,
                'email' => $o->email,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'grand_total' => $this->money($o->grand_total, $o->currency_code),
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Son 30 günde en çok satılan ürünler (adet bazında).
     *
     * @return array<int, array{sku: string, name: string, product_id: string|null, quantity: int, revenue: string}>
     */
    public function topProducts(int $limit = 5): array
    {
        [$from] = $this->periods();

        $tl = 'case when orders.currency_code = \'TRY\' then order_items.line_total '
            .'else order_items.line_total * coalesce(nullif(orders.exchange_rate, 0), 1) end';

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $from)
            ->whereNotIn('orders.status', $this->excludedStatuses())
            ->where('orders.payment_status', '!=', PaymentStatus::Refunded->value)
            ->selectRaw("order_items.sku as sku, max(order_items.name) as name, max(order_items.product_id) as product_id, sum(order_items.quantity) as qty, sum({$tl}) as revenue")
            ->groupBy('order_items.sku')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'product_id' => $row->product_id,
                'quantity' => (int) $row->qty,
                'revenue' => $this->money($row->revenue),
            ])
            ->all();
    }

    /**
     * Bekleyen işler sayaçları; yetkisi olmayan kalemler null döner.
     *
     * @param  array<string, bool>  $can
     * @return array{quotes: int|null, returns: int|null, awaiting_payment: int|null, out_of_stock: int|null}
     */
    public function pendingTasks(array $can): array
    {
        return [
            'quotes' => ($can['quotes'] ?? false)
                ? Quote::query()->whereIn('status', QuoteStatus::awaitingAdmin())->count()
                : null,
            'returns' => ($can['returns'] ?? false)
                ? ReturnRequest::query()->where('status', ReturnStatus::Pending->value)->count()
                : null,
            'awaiting_payment' => ($can['orders'] ?? false)
                ? Order::query()
                    ->where('payment_method', 'bank_transfer')
                    ->where('payment_status', PaymentStatus::Pending->value)
                    ->whereNotIn('status', $this->excludedStatuses())
                    ->count()
                : null,
            // Ürün listesindeki "stok=out" filtresiyle aynı tanım.
            'out_of_stock' => ($can['products'] ?? false)
                ? Product::query()->where('stock', '<=', 0)->count()
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivity(int $limit = 8): array
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'subject_label' => ActivitySubjectLabels::for($a->subject_type),
                'causer_name' => $a->causer?->getAttribute('name'),
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Builder<Order>
     */
    public function revenueQuery(): Builder
    {
        return Order::query()
            ->whereNotIn('status', $this->excludedStatuses())
            ->where('payment_status', '!=', PaymentStatus::Refunded->value);
    }

    /**
     * @return array<int, string>
     */
    private function excludedStatuses(): array
    {
        return [OrderStatus::Cancelled->value, OrderStatus::Failed->value];
    }

    /**
     * Sipariş tutarını ana para birimine (siparişteki kurlarla) çeviren SQL ifadesi.
     */
    public function tlExpression(string $column): string
    {
        return Money::sqlToBase($column);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periods(): array
    {
        $from = CarbonImmutable::today()->subDays(self::PERIOD_DAYS - 1);

        return [$from, $from->subDays(self::PERIOD_DAYS)];
    }

    /**
     * Önceki döneme göre yüzde değişim; önceki dönem 0 ise null.
     */
    private function change(BigNumber|int $current, BigNumber|int $previous): ?float
    {
        return Money::change($current, $previous);
    }

    private function money(BigNumber|int|string|null $amount, ?string $code = null): string
    {
        return Money::formatIn($amount, $code);
    }
}
