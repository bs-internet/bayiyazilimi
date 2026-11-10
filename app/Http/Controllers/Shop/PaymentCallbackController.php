<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Payments\HandlesCallbacks;
use App\Support\Payments\HostedCardGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\ProvidesInstallments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ödeme sağlayıcısı geri dönüşleri. Sürücü `HandlesCallbacks` uygulamalıdır.
 *
 * - `notify`: sağlayıcının sunucudan sunucuya bildirimi (oturum ve CSRF dışı).
 * - `return`: müşterinin tarayıcıyla dönüşü (3D sonrası); sürücü sipariş sayfasına yönlendirir.
 */
class PaymentCallbackController extends Controller
{
    public function notify(Request $request, string $gateway, PaymentRegistry $payments): Response
    {
        return $this->driver($gateway, $payments)->handleCallback($request);
    }

    public function return(Request $request, string $gateway, PaymentRegistry $payments): Response
    {
        return $this->driver($gateway, $payments)->handleReturn($request);
    }

    /**
     * Kart ödeme sayfası: bekleyen ödemenin sürücüsü sağlayıcı sayfasını gösterir.
     */
    public function pay(Request $request, string $reference, PaymentRegistry $payments): Response
    {
        $payment = Payment::query()->withoutGlobalScopes()->where('merchant_oid', $reference)->firstOrFail();
        $driver = $payments->get($payment->method);

        abort_unless($driver instanceof HostedCardGateway, 404);

        $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->find($payment->order_id) : null;
        $target = $payment->order_id !== null ? ($order?->customerUrl() ?? route('home')) : route('app.shop.payments.index');

        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->to($target)->with('toast', ['type' => 'info', 'message' => __('Bu ödeme zaten sonuçlanmış.')]);
        }

        // İptal edilmiş siparişe ödeme alınmaz: sağlayıcıya hiç gidilmez.
        if ($order?->status === OrderStatus::Cancelled) {
            return redirect()->to($target)->with('toast', ['type' => 'warning', 'message' => __('Bu sipariş iptal edildi; ödeme alınmadı.')]);
        }

        return $driver->renderPayment($payment, $request);
    }

    /**
     * Taksit seçenekleri (ve BIN ile kart bilgisi), sağlayıcı API'sinden.
     */
    public function installments(Request $request, string $gateway, PaymentRegistry $payments): JsonResponse
    {
        $driver = $payments->get($gateway);

        abort_unless($driver instanceof ProvidesInstallments, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'bin' => ['nullable', 'digits_between:6,8'],
        ]);

        try {
            return response()->json($driver->installments(Money::round((string) $data['amount']), strtoupper($data['currency'] ?? 'TRY'), $data['bin'] ?? null));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['card' => null, 'rows' => [], 'error' => __('Taksit bilgisi alınamadı.')], 200);
        }
    }

    private function driver(string $key, PaymentRegistry $payments): HandlesCallbacks
    {
        $driver = $payments->get($key);

        abort_unless($driver instanceof HandlesCallbacks, 404);

        return $driver;
    }
}
