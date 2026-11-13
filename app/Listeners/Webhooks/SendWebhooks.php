<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Events\Shop\AccountTransactionRecordedEvent;
use App\Events\Shop\OrderInvoicedEvent;
use App\Events\Shop\OrderPaidEvent;
use App\Events\Shop\OrderPlacedEvent;
use App\Events\Shop\OrderStatusChangedEvent;
use App\Events\Shop\QuoteRequestedEvent;
use App\Events\Shop\ReturnRequestedEvent;
use App\Events\Shop\StockLowEvent;
use App\Http\Resources\Api\V1\AccountTransactionResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Order;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookService;

/**
 * Çekirdek olaylarını webhook olarak gönderir. Veri, REST API'nin aynı
 * kaynağının gösterimidir (sipariş → OrderResource …). Etkin webhook yoksa
 * hiçbir şey yapmaz (tek sorgu).
 */
class SendWebhooks
{
    public function __construct(
        private readonly WebhookService $webhooks,
    ) {}

    public function handleOrderPlaced(OrderPlacedEvent $event): void
    {
        $this->send(WebhookEvents::ORDER_CREATED, fn () => self::order($event->order));
    }

    public function handleOrderStatusChanged(OrderStatusChangedEvent $event): void
    {
        $this->send(WebhookEvents::ORDER_STATUS_CHANGED, fn () => [
            'previous_status' => $event->previous->value,
            'order' => self::order($event->order),
        ]);
    }

    public function handleOrderPaid(OrderPaidEvent $event): void
    {
        $this->send(WebhookEvents::ORDER_PAID, fn () => self::order($event->order));
    }

    public function handleOrderInvoiced(OrderInvoicedEvent $event): void
    {
        $this->send(WebhookEvents::ORDER_INVOICED, fn () => self::order($event->order));
    }

    public function handleAccountTransaction(AccountTransactionRecordedEvent $event): void
    {
        $this->send(WebhookEvents::ACCOUNT_TRANSACTION, fn () => [
            'account' => ['id' => $event->transaction->account_id, 'code' => $event->transaction->account()->value('code')],
            'transaction' => (new AccountTransactionResource($event->transaction))->resolve(),
        ]);
    }

    public function handleStockLow(StockLowEvent $event): void
    {
        $this->send(WebhookEvents::STOCK_LOW, fn () => (new ProductResource($event->product->loadMissing(['currency', 'stocks.warehouse'])))->resolve());
    }

    public function handleReturnRequested(ReturnRequestedEvent $event): void
    {
        $return = $event->returnRequest->loadMissing(['order:id,number', 'account:id,code']);

        $this->send(WebhookEvents::RETURN_REQUESTED, fn () => [
            'id' => $return->id,
            'number' => $return->number,
            'status' => $return->status->value,
            'order_number' => $return->order?->number,
            'account_code' => $return->account?->code,
            'reason' => $return->reason,
            'created_at' => $return->created_at?->toIso8601String(),
        ]);
    }

    public function handleQuoteRequested(QuoteRequestedEvent $event): void
    {
        $quote = $event->quote->loadMissing('account:id,code');

        $this->send(WebhookEvents::QUOTE_REQUESTED, fn () => [
            'id' => $quote->id,
            'number' => $quote->number,
            'status' => $quote->status->value,
            'account_code' => $quote->account?->code,
            'customer_name' => $quote->customer_name,
            'company' => $quote->company,
            'email' => $quote->email,
            'created_at' => $quote->created_at?->toIso8601String(),
        ]);
    }

    /** Veri yalnız abone olan adres varsa hazırlanır. */
    private function send(string $event, callable $data): void
    {
        if (! WebhookEndpoint::query()->where('active', true)->exists()) {
            return;
        }

        $this->webhooks->dispatch($event, $data());
    }

    /** @return array<string, mixed> */
    private static function order(Order $order): array
    {
        return (new OrderResource($order->fresh(OrderResource::relations()) ?? $order))->resolve();
    }
}
