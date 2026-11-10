<?php

declare(strict_types=1);

namespace App\Http\Responses\Auth;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse;

/**
 * Şifre sıfırlama isteğinde kayıtsız adres ve yakın zamanda istek yapılmış adres,
 * başarılı istekle aynı yanıtı alır: yanıttan adresin kayıtlı olup olmadığı anlaşılmaz.
 */
class PasswordResetLinkFailedResponse extends FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        if (in_array($this->status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => Password::RESET_LINK_SENT])->toResponse($request);
        }

        return parent::toResponse($request);
    }
}
