<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Üye alanında bağlanan hesap değerleri. `NO_ACCOUNT`: oturumdaki üyenin
 * hesabı yok — hesap kapsamlı sorgular hiçbir kayıt döndürmez (kapsam hiç
 * uygulanmasaydı tüm hesapların kayıtları görünürdü).
 */
final class BelongsToAccountScope
{
    public const NO_ACCOUNT = '__no_account__';
}
