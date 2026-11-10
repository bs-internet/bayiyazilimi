<?php

declare(strict_types=1);

namespace App\Services\Panel\Settings\ApiTokens;

use App\Events\Panel\Settings\ApiTokens\ApiTokenCreatedEvent;
use App\Events\Panel\Settings\ApiTokens\ApiTokenRevokedEvent;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Api\ApiAbilities;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * REST API anahtarları: yöneticilerin anahtarlarını listeler, oluşturur ve
 * iptal eder. Anahtar oluşturan yöneticiye aittir; API o kullanıcı olarak
 * çalışır. Düz anahtar yalnız oluşturulduğunda döner (veritabanında özeti durur).
 */
class ApiTokenService
{
    /**
     * Tüm yöneticilerin anahtarları (sahibiyle), en yeni önce.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function all(): Collection
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->withTrashed()->admin()->select('id'))
            ->with(['tokenable' => fn ($query) => $query->withTrashed()])
            ->latest()
            ->get();
    }

    /**
     * @param  list<string>  $abilities
     * @return array{token: PersonalAccessToken, plain: string}
     */
    public function create(User $owner, string $name, array $abilities, ?CarbonImmutable $expiresAt): array
    {
        $abilities = array_values(array_intersect(ApiAbilities::keys(), $abilities));

        $result = $owner->createToken($name, $abilities, $expiresAt?->endOfDay());

        /** @var PersonalAccessToken $token */
        $token = $result->accessToken;

        event(new ApiTokenCreatedEvent($token, $owner));

        return ['token' => $token, 'plain' => $result->plainTextToken];
    }

    public function revoke(PersonalAccessToken $token, User $causer): void
    {
        $token->loadMissing(['tokenable' => fn ($query) => $query->withTrashed()]);
        $owner = $token->tokenable instanceof User ? $token->tokenable->name : null;
        $id = (string) $token->getKey();
        $name = (string) $token->name;

        $token->delete();

        event(new ApiTokenRevokedEvent($id, $name, $owner, $causer));
    }
}
