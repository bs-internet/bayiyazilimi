<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Enums\Shop\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Shop\OrderService;
use App\Support\Payments\BaseGateway;
use App\Support\Payments\HandlesCallbacks;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentStart;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modül gibi davranan sahte kart sürücüsü: bekleyen ödeme açar, kendi ödeme
 * sayfasına yönlendirir, bildirimle ödemeyi tamamlar.
 */
final class FakeCardGateway extends BaseGateway implements HandlesCallbacks
{
    public function key(): string
    {
        return 'fake_card';
    }

    public function label(): string
    {
        return 'Test Kartı';
    }

    public function available(PaymentContext $context): bool
    {
        return true;
    }

    public function start(Order $order): PaymentStart
    {
        $payment = $order->payments()->create([
            'account_id' => $order->account_id, 'method' => $this->key(), 'provider' => 'fake',
            'merchant_oid' => Payment::newReference(), 'amount' => $order->grand_total,
            'currency_code' => $order->currency_code, 'status' => PaymentStatus::Pending,
        ]);

        return PaymentStart::redirect('/test-kart/'.$payment->merchant_oid);
    }

    public function pendingTimeout(): ?int
    {
        return 30;
    }

    public function handleCallback(Request $request): Response
    {
        $payment = Payment::query()->where('merchant_oid', $request->input('oid'))->firstOrFail();
        app(OrderService::class)->markPaid($payment->order, null, 'Kartla ödendi.', $payment);

        return response('OK');
    }

    public function handleReturn(Request $request): Response
    {
        return redirect('/');
    }
}
