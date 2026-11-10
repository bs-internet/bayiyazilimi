<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Http\Controllers\App\Shop\Concerns\ResolvesMemberArea;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Shop\QuickOrderService;
use App\Services\Shop\ReturnService;
use App\Support\Money;
use App\Support\Pagination;
use Brick\Math\BigNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Üye alanı: Siparişlerim. Order BelongsToAccount olduğundan sorgular
 * bağlı hesaba (bind_account) otomatik kapsanır.
 */
class OrderController extends Controller
{
    use ResolvesMemberArea;

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->withCount('items')
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'payment_status_label' => $o->payment_status->label(),
                'payment_method_label' => $o->paymentMethodLabel(),
                'items_count' => $o->items_count,
                'grand_total' => self::money($o->grand_total, $o->currency_code),
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        return Inertia::render($this->page('orders/index'), [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        // Rota bağlama, hesap kapsamı (bind_account) devreye girmeden çalışabilir; sahipliği açıkça doğrula.
        abort_unless($order->account_id !== null && $order->account_id === $request->user()?->account_id, 404);

        $order->load(['items', 'histories', 'carrier']);

        return Inertia::render($this->page('orders/show'), [
            // Havale / EFT ödemesi bekleyen siparişte hesap bilgileri (yöntemin
            // açıklamasında "sipariş sayfasında yer alır" denir).
            'bankAccounts' => $order->payment_method === 'bank_transfer'
                && ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
                && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Failed], true)
                    ? BankAccount::forDisplay()
                    : null,
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'payment_method_label' => $order->paymentMethodLabel(),
                'due_date' => $order->due_date?->toDateString(),
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'customer_note' => $order->customer_note,
                'cargo_company' => $order->carrier?->name ?? $order->cargo_company,
                'tracking_url' => $order->carrier?->trackingLink($order->tracking_number),
                'total_desi' => Money::decimal($order->total_desi),
                'tracking_number' => $order->tracking_number,
                'subtotal' => self::money($order->subtotal, $order->currency_code),
                // Kampanya indirimi: ara toplam indirim düşülmüş tutardır; özet için indirim öncesi tutar ve kampanya satırları.
                'gross_subtotal' => Money::decimal($order->discount_total)->isPositive()
                    ? self::money(Money::add($order->subtotal, $order->discount_total), $order->currency_code)
                    : null,
                'discount_total' => Money::decimal($order->discount_total)->isPositive() ? self::money($order->discount_total, $order->currency_code) : null,
                'discounts' => Money::decimal($order->discount_total)->isPositive()
                    ? $order->items
                        ->filter(fn (OrderItem $i) => Money::decimal($i->discount_amount)->isPositive())
                        ->groupBy(fn ($i) => (string) ($i->getAttribute('campaign') ?: __('Kampanya indirimi')))
                        ->map(fn ($items, $label) => [
                            'label' => (string) $label,
                            'amount' => self::money(Money::add(...$items->pluck('discount_amount')), $order->currency_code),
                        ])
                        ->values()
                    : [],
                'tax_total' => self::money($order->tax_total, $order->currency_code),
                'shipping_total' => Money::decimal($order->shipping_total)->isPositive() ? self::money($order->shipping_total, $order->currency_code) : __('Ücretsiz'),
                'payment_adjustment' => ! Money::decimal($order->payment_adjustment)->isZero() ? self::money($order->payment_adjustment, $order->currency_code) : null,
                'payment_adjustment_label' => $order->payment_adjustment_label,
                'grand_total' => self::money($order->grand_total, $order->currency_code),
                'created_at' => $order->created_at?->toIso8601String(),
                'public_url' => $order->publicUrl(),
                'can_return' => app(ReturnService::class)->isEligible($order)
                    && array_sum(app(ReturnService::class)->returnableQuantities($order)) > 0,
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'unit_price' => self::money($i->unit_price, $order->currency_code),
                    'line_total' => self::money($i->line_total, $order->currency_code),
                    'campaign' => $i->campaign,
                    'discount' => Money::decimal($i->discount_amount)->isPositive() ? self::money($i->discount_amount, $order->currency_code) : null,
                ])->values(),
                'histories' => $order->histories->map(fn ($h) => [
                    'id' => $h->id,
                    'status_label' => $h->status?->label(),
                    'note' => $h->notify_customer || $h->user_id === null ? $h->note : null,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Tekrar sipariş: ürünler sepete eklenir; alanın sepetine gidilir (bayide
     * Sipariş ver sayfası, vitrin Hesabım'da vitrin sepeti).
     */
    public function reorder(Request $request, Order $order, QuickOrderService $quick): RedirectResponse
    {
        abort_unless($order->account_id !== null && $order->account_id === $request->user()?->account_id, 404);

        $result = $quick->reorder($order);
        $added = count($result['added']);
        $failed = count($result['failed']);

        return to_route($this->cartRoute())->with('toast', match (true) {
            $added > 0 && $failed === 0 => ['type' => 'success', 'message' => __(':added ürün sepete eklendi.', ['added' => $added])],
            $added > 0 => ['type' => 'warning', 'message' => __(':added ürün sepete eklendi, :failed ürün eklenemedi.', ['added' => $added, 'failed' => $failed])],
            default => ['type' => 'error', 'message' => __('Hiçbir ürün sepete eklenemedi.')],
        });
    }

    public static function money(BigNumber|int|string|null $amount, string $code): string
    {
        return Money::formatIn($amount, $code);
    }
}
