<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Models\Order;
use App\Support\Payments\BaseGateway;
use App\Support\Payments\HandlesCallbacks;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentStart;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditCardGateway extends BaseGateway implements HandlesCallbacks
{
    public function key(): string
    {
        return 'audit_card';
    }

    public function label(): string
    {
        return 'Denetim Kartı';
    }

    public function available(PaymentContext $context): bool
    {
        return true;
    }

    public function start(Order $order): PaymentStart
    {
        return PaymentStart::none();
    }

    public function handleCallback(Request $request): Response
    {
        return response('OK');
    }

    public function handleReturn(Request $request): Response
    {
        return redirect('/');
    }
}
