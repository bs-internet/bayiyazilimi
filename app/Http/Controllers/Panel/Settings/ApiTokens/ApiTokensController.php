<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Settings\ApiTokens;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Settings\ApiTokens\StoreApiTokenRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Panel\Settings\ApiTokens\ApiTokenService;
use App\Support\Api\ApiAbilities;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ayarlar → API Anahtarları: REST API (/api/v1) anahtarlarının listesi ve
 * iptali; oluşturma ayrı sayfada (düz anahtar listede yalnız bir kez gösterilir).
 */
class ApiTokensController extends Controller
{
    public function index(Request $request, ApiTokenService $service): Response
    {
        $labels = ApiAbilities::all();

        return Inertia::render('panel/settings/api-tokens/index', [
            'tokens' => $service->all()->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'owner' => $token->tokenable instanceof User ? $token->tokenable->name : null,
                'abilities' => array_values(array_map(
                    fn (string $ability): array => ['value' => $ability, 'label' => (string) ($labels[$ability] ?? $ability)],
                    (array) $token->abilities,
                )),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'expired' => $token->expires_at?->isPast() ?? false,
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values()->all(),
            // Yeni anahtar: oluşturma sonrası tek seferlik (oturum flash'ı).
            'plainToken' => $request->session()->get('api_token'),
            'baseUrl' => url('/api/v1'),
            'mcpUrl' => route('mcp'),
            'docsUrl' => 'https://api.bayiyazilimi.com',
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('panel/settings/api-tokens/create', [
            'abilities' => ApiAbilities::options($request->user()),
        ]);
    }

    public function store(StoreApiTokenRequest $request, ApiTokenService $service): RedirectResponse
    {
        $expires = $request->validated('expires_at');

        ['plain' => $plain] = $service->create(
            $request->user(),
            (string) $request->validated('name'),
            array_values((array) $request->validated('abilities')),
            $expires === null ? null : CarbonImmutable::parse((string) $expires),
        );

        return to_route('panel.settings.api-tokens.index')
            ->with('api_token', $plain)
            ->with('toast', ['type' => 'success', 'message' => __('API anahtarı oluşturuldu. Anahtarı şimdi kopyalayın; bir daha gösterilmez.')]);
    }

    public function destroy(Request $request, PersonalAccessToken $token, ApiTokenService $service): RedirectResponse
    {
        $service->revoke($token, $request->user());

        return to_route('panel.settings.api-tokens.index')
            ->with('toast', ['type' => 'success', 'message' => __(':name anahtarı iptal edildi.', ['name' => $token->name])]);
    }
}
