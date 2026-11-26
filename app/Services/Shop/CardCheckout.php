<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\PaymentStatus;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use App\Support\Payments\CardFormGateway;
use Illuminate\Support\Facades\Log;

/**
 * Kart ekranıyla ödenen sipariş ödeme alınana kadar açılmaz:
 *
 * 1. `start()` ödeme formunu siparişle aynı denetimlerden geçirir (önizleme), sipariş
 *    yerine bekleyen bir ödeme kaydı açar; formun bilgileri ve sepetin anlık görüntüsü
 *    `payments.checkout`'ta durur. Sepet boşalmaz, stok düşülmez, e-posta gitmez.
 * 2. Kart ekranı siparişin son halini `preview()` ile gösterir.
 * 3. Sağlayıcı ödemeyi onaylayınca `place()` siparişi açar ve ödemeyi ona bağlar;
 *    vazgeçilen / başarısız ödemede sipariş hiç oluşmaz.
 */
class CardCheckout
{
    /** Kart ekranına geçerken ödeme formunda girilenler (oturumda; sipariş açılınca silinir). */
    private const FORM_KEY = 'shop.checkout.form';

    /**
     * Ödeme formuna dönen müşterinin son girdiği bilgiler (yoksa null). Sözleşme onayları
     * bilerek saklanmaz: her gönderimde yeniden işaretlenir.
     *
     * @return array<string, mixed>|null
     */
    public static function lastForm(): ?array
    {
        $data = session(self::FORM_KEY);

        return is_array($data) ? $data : null;
    }

    public static function forgetForm(): void
    {
        session()->forget(self::FORM_KEY);
    }

    public function __construct(
        private readonly OrderService $orders,
        private readonly CurrencyManager $currencies,
    ) {}

    /**
     * @param  array<string, mixed>  $data  doğrulanmış ödeme formu
     */
    public function start(Basket $cart, array $data, ?User $user, Currency $currency, ?string $ip, CardFormGateway $gateway, string $backUrl): Payment
    {
        $preview = $this->orders->previewFromCart($cart, $data, $user, $currency, $ip);

        unset($data['checkout_token'], $data['expected_total']);

        // Müşteri kart ekranından ödeme formuna dönerse girdikleri yerinde durur.
        session()->put(self::FORM_KEY, $data);

        return Payment::query()->withoutGlobalScopes()->create([
            'account_id' => $user?->account_id,
            'order_id' => null,
            'method' => $gateway->key(),
            'provider' => $gateway->key(),
            'merchant_oid' => Payment::newReference('K'),
            'amount' => $preview->grand_total,
            'currency_code' => $currency->code,
            'status' => PaymentStatus::Pending,
            'note' => __('Sipariş ödemesi'),
            'user_id' => $user?->id,
            'checkout' => [
                'data' => $data,
                'items' => $cart->raw(),
                'currency' => $currency->code,
                'ip' => $ip,
                'back_url' => $backUrl,
            ],
        ]);
    }

    /** Ödenecek siparişin son hali (kaydedilmemiş); artık verilemiyorsa null. */
    public function preview(Payment $payment): ?Order
    {
        $arguments = $this->arguments($payment);

        try {
            return $this->orders->previewFromCart(...$arguments);
        } catch (ShopException) {
            return null;
        } finally {
            $arguments[0]->clear();
        }
    }

    /**
     * Kart bilgisi sağlayıcıya gitmeden son kontrol: sipariş hâlâ verilebilir ve tutarı
     * ödeme kaydıyla aynı mı (fiyat, stok, kampanya değişmiş olabilir). Sorun yoksa null.
     */
    public function problem(Payment $payment): ?string
    {
        $arguments = $this->arguments($payment);

        try {
            $order = $this->orders->previewFromCart(...$arguments);
        } catch (ShopException $e) {
            return $e->getMessage();
        } finally {
            $arguments[0]->clear();
        }

        return Money::round($order->grand_total)->isEqualTo(Money::round($payment->amount))
            ? null
            : __('Sepetinizin tutarı değişti; lütfen siparişinizi kontrol edip yeniden onaylayın.');
    }

    /**
     * Ödeme alındı: sipariş açılır, ödeme kaydı siparişe bağlanır. Açılamazsa (ödeme
     * sırasında stok tükendi, fiyat değişti) null; ödeme siparişsiz kalır ve hata
     * yöneticiye bildirilir (iade ya da elle sipariş).
     */
    public function place(Payment $payment): ?Order
    {
        [$cart, $data, $user, $currency, $ip] = $this->arguments($payment);

        try {
            return $this->orders->placeFromCartWithPayment($cart, [...$data, 'expected_total' => Money::str($payment->amount)], $user, $currency, $payment, $ip);
        } catch (ShopException $e) {
            $cart->clear();
            Log::error('Kartla ödeme alındı ama sipariş açılamadı.', ['payment' => $payment->merchant_oid, 'reason' => $e->getMessage()]);
            report(new \RuntimeException("Kartla ödeme alındı ama sipariş açılamadı ({$payment->merchant_oid}): {$e->getMessage()}"));

            return null;
        }
    }

    /** Ödeme formuna dönüş adresi. */
    public static function backUrl(Payment $payment): string
    {
        return (string) ($payment->checkout['back_url'] ?? route('home'));
    }

    /**
     * @return array{0: Basket, 1: array<string, mixed>, 2: ?User, 3: Currency, 4: ?string}
     */
    private function arguments(Payment $payment): array
    {
        $checkout = (array) $payment->checkout;

        return [
            Basket::snapshot($payment->merchant_oid, (array) ($checkout['items'] ?? [])),
            (array) ($checkout['data'] ?? []),
            $payment->user_id !== null ? User::query()->find($payment->user_id) : null,
            $this->currencies->find($checkout['currency'] ?? null) ?? $this->currencies->base(),
            isset($checkout['ip']) ? (string) $checkout['ip'] : null,
        ];
    }
}
