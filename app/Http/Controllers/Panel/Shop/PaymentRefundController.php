<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Shop\PaymentRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kart ödemesinin sağlayıcı üzerinden iadesi (sipariş detayındaki Ödemeler kartı)
 * ve "İşlemde" kalmış iadenin elle sonuçlandırılması.
 */
class PaymentRefundController extends Controller
{
    public function store(Request $request, Payment $payment, PaymentRefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], [], ['amount' => __('iade tutarı')]);

        $refunds->refund($payment, (string) $data['amount'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('İade sağlayıcıya iletildi ve kaydedildi.')]);
    }

    /** Sağlayıcı panelinde iade görülüyor: kayıt tamamlanır, cari ve sipariş işlenir. */
    public function confirm(Request $request, PaymentRefund $paymentRefund, PaymentRefundService $refunds): RedirectResponse
    {
        $refunds->confirm($paymentRefund, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('İade tamamlandı olarak kaydedildi.')]);
    }

    /** Sağlayıcıda iade yok: kayıt başarısız olur, tutar yeniden iade edilebilir. */
    public function abandon(Request $request, PaymentRefund $paymentRefund, PaymentRefundService $refunds): RedirectResponse
    {
        $refunds->abandon($paymentRefund, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('İade yapılmadı olarak kaydedildi; tutar yeniden iade edilebilir.')]);
    }
}
