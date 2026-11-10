<?php

declare(strict_types=1);

namespace App\Events\Panel;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bir yönetici, başka bir kullanıcının (panel kullanıcısı ya da üye) iki adımlı
 * doğrulamasını sıfırladı.
 */
class TwoFactorResetEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly User $causer,
    ) {}
}
