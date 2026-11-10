<?php

declare(strict_types=1);

namespace App\Exceptions\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Mağaza iş kurallarından doğan, kullanıcıya gösterilecek uyarılar
 * (yetersiz stok, geçersiz durum geçişi vb.). Genel 500 yerine geri
 * yönlendirip uyarı mesajı gösterir; REST API'de 422 + `message` döner.
 */
class ShopException extends RuntimeException
{
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->is('api/*')) {
            return new JsonResponse(['message' => $this->getMessage()], 422);
        }

        return back()
            ->withInput()
            ->with('toast', ['type' => 'warning', 'message' => $this->getMessage()])
            ->with('error', $this->getMessage());
    }
}
