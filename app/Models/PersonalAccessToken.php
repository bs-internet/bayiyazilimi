<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Api\ApiAbilities;
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

    /**
     * Anahtarın yetkisi + sahibinin panel izni. Yetki anahtar oluşturulurken
     * sahibinin izniyle sınırlanır; sahibinin rolü sonradan değişirse anahtar
     * kaybettiği izinleri kullanamaz (`abilities:` ara katmanı, MCP araçları ve
     * /api/v1 meta yanıtı hep bu metottan geçer).
     *
     * @param  string  $ability
     */
    public function can($ability)
    {
        if (! parent::can($ability)) {
            return false;
        }

        $owner = $this->tokenable;

        return $owner instanceof User && ApiAbilities::allowedFor($owner, (string) $ability);
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
