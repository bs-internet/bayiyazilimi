<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * REST API anahtarı (Sanctum kişisel erişim anahtarı). Uygulamanın diğer
 * modelleri gibi UUID anahtarlıdır; düz anahtar "{id}|{gizli}" biçimindedir
 * ve yalnız oluşturulduğunda bir kez gösterilir (veritabanında özeti durur).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
