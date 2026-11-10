<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\ReturnReason;
use App\Enums\Shop\ReturnStatus;
use App\Events\Shop\ReturnRequestedEvent;
use App\Events\Shop\ReturnStatusChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * İade talepleri: üyenin talep açması ve yöneticinin süreci yürütmesi
 * (Beklemede → Onaylandı → Ürün Teslim Alındı → İade Tamamlandı / Reddedildi).
 */
class ReturnService
{
    public function __construct(
        private readonly OrderStockService $stock,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Bu sipariş iade talebine uygun mu? (kargolanmış/tamamlanmış ve süre içinde)
     */
    public function isEligible(Order $order): bool
    {
        if (! in_array($order->status, [OrderStatus::Shipped, OrderStatus::Completed], true)) {
            return false;
        }

        // 0 gün = iade kapalı. Süre kargoya veriliş tarihinden sayılır (kargo tarihi
        // yoksa ödeme, o da yoksa sipariş tarihi) ve son günün sonuna kadar sürer.
        $days = ShopSettings::int('return_days');

        if ($days <= 0) {
            return false;
        }

        $start = $order->shipped_at ?? $order->paid_at ?? CarbonImmutable::parse($order->created_at);

        return $start->addDays($days)->endOfDay()->isFuture();
    }

    /**
     * Sipariş kalemi başına iade edilebilecek kalan adet (açık/onaylı talepler düşülür).
     *
     * @return array<string, int> order_item_id => adet
     */
    public function returnableQuantities(Order $order): array
    {
        $order->loadMissing('items');

        $requested = ReturnRequestItem::query()
            ->whereHas('returnRequest', fn ($q) => $q->where('order_id', $order->id)->where('status', '!=', ReturnStatus::Rejected->value))
            ->selectRaw('order_item_id, sum(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        return $order->items
            ->mapWithKeys(fn (OrderItem $i) => [$i->id => max(0, $i->quantity - (int) ($requested[$i->id] ?? 0))])
            ->all();
    }

    /**
     * @param  array{reason: string, customer_note?: ?string, items: array<string, int|string>}  $data
     */
    public function request(Order $order, User $user, array $data): ReturnRequest
    {
        if ($order->account_id === null || $order->account_id !== $user->account_id) {
            throw new ShopException(__('Bu sipariş için iade talebi oluşturamazsınız.'));
        }

        if (! $this->isEligible($order)) {
            $days = ShopSettings::int('return_days');

            throw new ShopException($days <= 0
                ? __('İade talebi kabul edilmiyor.')
                : __('İade talebi yalnızca kargoya verilmiş/tamamlanmış siparişler için, :days gün içinde oluşturulabilir.', ['days' => $days]));
        }

        $return = DB::transaction(function () use ($order, $user, $data) {
            // Sipariş kilitli: aynı siparişe eşzamanlı iki talep, kalan adedi birlikte aşamaz.
            Order::query()->withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->value('id');

            $available = $this->returnableQuantities($order);
            $lines = [];

            foreach ($data['items'] as $itemId => $qty) {
                $qty = (int) $qty;

                if ($qty <= 0) {
                    continue;
                }

                if (! array_key_exists($itemId, $available)) {
                    throw new ShopException(__('Geçersiz sipariş kalemi.'));
                }

                if ($qty > $available[$itemId]) {
                    throw new ShopException(__('Bir kalem için en fazla :available adet iade talep edebilirsiniz.', ['available' => $available[$itemId]]));
                }

                $lines[$itemId] = $qty;
            }

            if ($lines === []) {
                throw new ShopException(__('İade etmek istediğiniz ürün ve adedi seçin.'));
            }

            $return = ReturnRequest::create([
                'number' => OrderService::nextNumber('R', 'return_requests'),
                'order_id' => $order->id,
                'account_id' => $order->account_id,
                'user_id' => $user->id,
                'status' => ReturnStatus::Pending,
                'reason' => ReturnReason::from($data['reason']),
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            foreach ($lines as $itemId => $qty) {
                $return->items()->create(['order_item_id' => $itemId, 'quantity' => $qty]);
            }

            return $return;
        });

        ReturnRequestedEvent::dispatch($return);

        return $return;
    }

    /**
     * Talebin iade değeri: kalemlerin sipariş satır tutarına (indirim ve KDV dahil)
     * düşen payı. İade tutarı bunu aşamaz.
     */
    public function returnValue(ReturnRequest $return): BigDecimal
    {
        $return->loadMissing('items.orderItem');

        return Money::round(Money::add(...$return->items->map(function (ReturnRequestItem $i) {
            $item = $i->orderItem;

            return $item && $item->quantity > 0 ? Money::div(Money::decimal($item->line_total)->multipliedBy($i->quantity), $item->quantity, 4) : 0;
        })->all()));
    }

    /**
     * Yönetici durum değişikliği. Geçiş haritası (ReturnStatus::canTransitionTo)
     * kilit altında, güncel durum üzerinden denetlenir; aynı durum yeniden
     * seçilirse (sonuçlanmamış talepte) yalnız not güncellenir.
     *
     * @param  array{admin_note?: ?string, refund_amount?: float|string|null, restock?: bool, mark_refunded?: bool}  $data
     */
    public function changeStatus(ReturnRequest $return, ReturnStatus $status, User $causer, array $data = []): void
    {
        $previous = DB::transaction(function () use ($return, $status, $causer, $data): ReturnStatus {
            // Çift gönderim / iki yönetici: talep kilitli yeniden okunur (stoğa alma ve
            // cari kaydı bir kez yapılır).
            $locked = ReturnRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($return->id);
            $return->setRawAttributes($locked->getAttributes(), true);
            $previous = $return->status;
            $changed = $previous !== $status;

            if ($previous->isFinal()) {
                throw new ShopException(__('Sonuçlanmış iade talebinin durumu değiştirilemez.'));
            }

            if ($changed && ! $previous->canTransitionTo($status)) {
                throw new ShopException(__(':from durumundaki iade talebi :to yapılamaz.', ['from' => $previous->label(), 'to' => $status->label()]));
            }

            if ($changed && $status === ReturnStatus::Rejected && $return->restocked) {
                throw new ShopException(__('Ürünleri stoğa alınmış iade talebi reddedilemez.'));
            }

            // Onay anında sipariş yeniden denetlenir (bu arada iptal edilmiş olabilir).
            if ($changed && $status === ReturnStatus::Approved && ! in_array($return->order?->status, [OrderStatus::Shipped, OrderStatus::Completed], true)) {
                throw new ShopException(__('Siparişi kargoya verilmiş / tamamlanmış durumda olmayan iade talebi onaylanamaz.'));
            }

            $fill = [
                'status' => $status,
                'admin_note' => $data['admin_note'] ?? $return->admin_note,
            ];

            if (array_key_exists('refund_amount', $data) && $data['refund_amount'] !== null && $data['refund_amount'] !== '') {
                $fill['refund_amount'] = Money::round(str_replace(',', '.', (string) $data['refund_amount']));
                $max = $this->returnValue($return);

                if ($fill['refund_amount']->isGreaterThan($max)) {
                    throw new ShopException(__('İade tutarı, iade edilen ürünlerin sipariş tutarını (:amount) aşamaz.', ['amount' => Money::number($max)]));
                }
            }

            if ($status->isFinal()) {
                $fill['resolved_at'] = now();
                $fill['resolved_by'] = $causer->id;
            }

            // Teslim alınan/iadesi tamamlanan ürünler istenirse stoğa geri eklenir (bir kez).
            if (($data['restock'] ?? false) && ! $return->restocked && in_array($status, [ReturnStatus::Received, ReturnStatus::Refunded], true)) {
                $this->restock($return, $causer);
                $fill['restocked'] = true;
            }

            $return->forceFill($fill)->save();

            if ($changed && $status === ReturnStatus::Refunded) {
                $this->settleRefund($return, $causer, (bool) ($data['mark_refunded'] ?? false));
            }

            return $previous;
        });

        ReturnStatusChangedEvent::dispatch($return->fresh(), $previous, $causer);
    }

    /**
     * İade tamamlandı: tutar cariye alacak; para da geri ödendiyse aynı tutarda
     * borç. Sipariş ancak tüm tutarı iade edildiyse "İade Edildi" olur.
     */
    private function settleRefund(ReturnRequest $return, User $causer, bool $moneyReturned): void
    {
        if (Money::decimal($return->refund_amount)->isPositive()) {
            $this->ledger->recordReturn($return, $return->refund_amount, $moneyReturned, $causer);
        }

        if (! $moneyReturned) {
            return;
        }

        $order = Order::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($return->order_id);
        $refundedTotal = Money::add(...ReturnRequest::query()->withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('status', ReturnStatus::Refunded)
            ->pluck('refund_amount')->all());
        $full = $refundedTotal->isGreaterThanOrEqualTo(Money::decimal($order->grand_total));

        if ($full && in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Partial], true)) {
            $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
        }

        $order->histories()->create([
            'status' => $order->status,
            'note' => $full
                ? __('İade :number tamamlandı, ödeme iade edildi.', ['number' => $return->number])
                : __('İade :number tamamlandı, ödemenin :amount :currency kısmı iade edildi.', ['number' => $return->number, 'amount' => Money::number($return->refund_amount), 'currency' => $order->currency_code]),
            'user_id' => $causer->id,
        ]);
    }

    private function restock(ReturnRequest $return, User $causer): void
    {
        $return->loadMissing(['items.orderItem.product', 'order']);
        $warehouse = Warehouse::query()->find($return->order->warehouse_id) ?? Warehouse::default();

        if ($warehouse === null) {
            throw new ShopException(__('Stoğa eklemek için tanımlı bir depo yok.'));
        }

        foreach ($return->items as $item) {
            $product = $item->orderItem?->product;

            if ($product === null || ! $product->track_stock) {
                continue;
            }

            // Siparişte düştüğü depo/lokasyona döner; bulunamazsa siparişin deposuna.
            $this->stock->restockFromOrder($return->order, $product, $item->quantity, $warehouse, __('İade :number', ['number' => $return->number]), $causer, $return);
        }
    }
}
