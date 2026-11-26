<?php

declare(strict_types=1);

namespace App\Support\Payments\Gateways;

use App\Exceptions\Shop\ShopException;
use App\Models\Payment;
use App\Services\Shop\ShopSettings;
use App\Support\Payments\CardDetails;
use App\Support\Payments\CardFormGateway;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kart ekranı önizlemesi: sanal pos bağlanmadan önce kartla ödeme akışını (ödeme
 * formu → kart ekranı) göstermek için. Satış Ayarları'ndan açılır, varsayılan kapalı.
 *
 * Hiçbir zaman ödeme almaz: "Ödeme Yap" kartı doğrular ama sağlayıcıya gitmez,
 * müşteriyi uyarıyla kart ekranına geri döndürür. Sipariş "ödeme bekliyor" kalır ve
 * süresi dolunca (pendingTimeout) ödeme başarısız sayılır, stok geri döner.
 * Gerçek sanal pos sürücüsü kurulunca kapatılır.
 */
final class CardPreviewGateway extends CardFormGateway
{
    public const KEY = 'card_preview';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Kredi / Banka Kartı');
    }

    public function notice(): ?string
    {
        return __('Kartla ödeme henüz devreye alınmadı; bu ekran önizlemedir ve kartınızdan çekim yapılmaz.');
    }

    protected function configured(): bool
    {
        return ShopSettings::bool('card_preview_enabled');
    }

    public function charge(Payment $payment, CardDetails $card, Request $request): Response
    {
        return back()->with('toast', ['type' => 'warning', 'message' => __('Kartla ödeme henüz devreye alınmadı; kartınızdan çekim yapılmadı. Siparişinizi havale / EFT ile tamamlayabilir ya da bizimle iletişime geçebilirsiniz.')]);
    }

    public function handleCallback(Request $request): Response
    {
        abort(404);
    }

    public function handleReturn(Request $request): Response
    {
        abort(404);
    }

    public function installments(BigDecimal $amount, string $currency, ?string $bin = null): array
    {
        return ['card' => null, 'rows' => []];
    }

    public function refund(Payment $payment, BigDecimal $amount): void
    {
        throw new ShopException(__('Önizleme ödemesi iade edilemez; bu yöntemle ödeme alınmaz.'));
    }
}
