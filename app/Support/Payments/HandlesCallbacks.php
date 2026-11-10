<?php

declare(strict_types=1);

namespace App\Support\Payments;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sağlayıcı geri dönüşleri: sunucudan sunucuya bildirim (`/odeme/bildirim/{key}`,
 * oturum ve CSRF dışı) ve müşterinin tarayıcıyla dönüşü (`/odeme/donus/{key}`).
 */
interface HandlesCallbacks
{
    public function handleCallback(Request $request): Response;

    public function handleReturn(Request $request): Response;
}
