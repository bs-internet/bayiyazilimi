<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Events\Panel\TwoFactorResetEvent;
use App\Exceptions\DefinitionGuardException;
use App\Models\User;

/**
 * Telefonunu / doğrulama uygulamasını kaybeden kullanıcının iki adımlı
 * doğrulamasını yönetici sıfırlar: gizli anahtar ve kurtarma kodları silinir,
 * kullanıcı yalnız şifresiyle girip doğrulamayı yeniden kurar.
 */
class TwoFactorResetService
{
    public function reset(User $user, User $causer): void
    {
        if ($user->is($causer)) {
            throw new DefinitionGuardException(__('Kendi iki adımlı doğrulamanızı Bilgilerim › Güvenlik ekranından yönetin.'));
        }

        // Super Admin'in doğrulamasını yalnız bir Super Admin sıfırlayabilir.
        if ($user->hasRole('Super Admin') && ! $causer->hasRole('Super Admin')) {
            throw new DefinitionGuardException(__('Bu kullanıcının iki adımlı doğrulamasını sıfırlama yetkiniz yok.'));
        }

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            throw new DefinitionGuardException(__('Bu kullanıcının iki adımlı doğrulaması açık değil.'));
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        TwoFactorResetEvent::dispatch($user, $causer);
    }
}
