<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Shop\CardCheckout;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kart bilgisinin mağazanın kendi kart ekranında girildiği sürücüler (sağlayıcının
 * doğrudan 3D Secure API'si). Akış HostedCardGateway ile aynıdır; farkı ödeme
 * sayfasıdır:
 *
 * 1. Ödeme formu → sipariş açılmaz, bekleyen ödeme kaydı (CardCheckout) → imzalı `/odeme/kart/{ref}`.
 *    Sipariş ödeme başarılı olunca `complete()` içinde açılır.
 * 2. Kart ekranı (çekirdek sayfa, tema değiştirebilir): girilen bilgiler, siparişin son
 *    hali ve kart formu. "Ödeme Yap" kart bilgisini `charge()`'a verir.
 * 3. `charge()` sağlayıcıya iletir ve müşteriyi 3D doğrulamaya götürür; sonuç
 *    `handleReturn()` / `handleCallback()` ile `complete()` ya da `fail()` olur.
 *
 * Kart bilgisi saklanmaz (bkz. CardDetails).
 */
abstract class CardFormGateway extends HostedCardGateway
{
    /**
     * Kart bilgisini sağlayıcıya iletir; yanıt genellikle bankanın 3D doğrulama sayfasıdır
     * (kendiliğinden gönderilen HTML form ya da yönlendirme).
     */
    abstract public function charge(Payment $payment, CardDetails $card, Request $request): Response;

    /** Kart ekranında formun üstünde gösterilecek uyarı (yoksa null). */
    public function notice(): ?string
    {
        return null;
    }

    /** Sunulan en yüksek taksit sayısı (1 = tek çekim; taksit seçimi gösterilmez). */
    public function maxInstallment(): int
    {
        return 1;
    }

    public function renderPayment(Payment $payment, Request $request): Response
    {
        $order = $payment->order_id !== null
            ? Order::query()->withoutGlobalScopes()->with('items', 'carrier')->find($payment->order_id)
            : null;
        $draft = $payment->checkout !== null;

        // Sipariş ödeme alınınca açılır; o zamana kadar son hali önizlemeyle gösterilir.
        if ($order === null && $draft) {
            $order = app(CardCheckout::class)->preview($payment);
        }

        return Inertia::render(self::component($request), [
            'payment' => [
                'reference' => $payment->merchant_oid,
                'method' => $this->label(),
                'amount' => Money::str($payment->amount),
                'amount_formatted' => self::currency($payment->currency_code)->format($payment->amount),
                'description' => $order !== null && $order->exists ? __('Sipariş :number', ['number' => $order->number]) : (string) $payment->note,
            ],
            'order' => $order !== null ? self::presentOrder($order) : null,
            'action' => URL::temporarySignedRoute('shop.payment.charge', now()->addHours(2), ['reference' => $payment->merchant_oid]),
            'csrfToken' => csrf_token(),
            'backUrl' => $draft ? CardCheckout::backUrl($payment) : ($order !== null ? $order->customerUrl() : route('app.shop.payments.index')),
            'notice' => $this->notice(),
            'maxInstallment' => max(1, min(12, $this->maxInstallment())),
        ])->toResponse($request);
    }

    /**
     * Bayi (giriş yapmış, bayi hesabı) bayi panelindeki sayfayı, diğerleri mağaza
     * sayfasını görür; mağaza sayfası etkin temada varsa temanınki açılır.
     */
    private static function component(Request $request): string
    {
        $account = $request->user()?->account_id !== null ? Account::query()->find($request->user()->account_id) : null;

        return $account?->isDealer() ? 'app/shop/payment/card' : 'shop/payment/card';
    }

    private static function currency(string $code): Currency
    {
        return Currency::withTrashed()->where('code', $code)->first()
            ?? Currency::query()->where('code', Money::baseCode())->firstOrFail();
    }

    /**
     * Kart ekranındaki sipariş özeti: iletişim, adresler, satırlar ve toplamlar
     * (ödeme formundaki özetle aynı biçim; CheckoutLine / BasketTotals).
     *
     * @return array<string, mixed>
     */
    private static function presentOrder(Order $order): array
    {
        $currency = self::currency($order->currency_code);
        $adjustment = Money::decimal($order->payment_adjustment ?? 0);
        $beforeAdjustment = Money::sub($order->grand_total, $adjustment);
        $carrier = $order->carrier?->name ?? $order->cargo_company;

        return [
            'number' => $order->exists ? $order->number : null,
            'email' => $order->email,
            'phone' => $order->phone,
            'billing' => self::address((array) $order->billing_address),
            'shipping' => self::address((array) $order->shipping_address),
            'customer_note' => $order->customer_note,
            'carrier' => $carrier,
            'lines' => $order->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'total_formatted' => $currency->format($item->line_total),
                'campaign' => null,
            ])->values()->all(),
            'totals' => [
                'discount' => Money::str($order->discount_total ?? 0),
                'discount_formatted' => Money::decimal($order->discount_total ?? 0)->isPositive() ? $currency->format($order->discount_total) : null,
                'subtotal' => Money::str($order->subtotal),
                'subtotal_formatted' => $currency->format($order->subtotal),
                'tax' => Money::str($order->tax_total),
                'tax_formatted' => $currency->format($order->tax_total),
                'shipping' => Money::str($order->shipping_total),
                'shipping_formatted' => Money::decimal($order->shipping_total)->isPositive() ? $currency->format($order->shipping_total) : __('Ücretsiz'),
                'total' => Money::str($beforeAdjustment),
                'total_formatted' => $currency->format($beforeAdjustment),
                'desi' => null,
                'carrier' => $carrier,
                'currency' => $order->currency_code,
                'campaign' => null,
                'nudge' => null,
            ],
            'adjustment' => $adjustment->isZero() ? null : [
                'label' => $order->payment_adjustment_label ?: __('Ödeme farkı'),
                'amount_formatted' => $currency->format($adjustment),
            ],
            'grand_total_formatted' => $currency->format($order->grand_total),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private static function address(array $address): array
    {
        $keys = ['name', 'company', 'tax_office', 'tax_number', 'identity_number', 'phone', 'address', 'district', 'city', 'postal_code'];

        return array_combine($keys, array_map(fn (string $key) => filled($address[$key] ?? null) ? (string) $address[$key] : null, $keys));
    }
}
