<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiAbilities;
use App\Support\BayiYazilimi;
use App\Support\Branding;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * API'nin kendisi: anahtarın kimliği ve yetkileri (GET /api/v1) ve makine
 * okunur OpenAPI tanımı (GET /api/v1/openapi.yaml, kimlik istemez).
 */
class MetaController extends Controller
{
    public const SPEC = 'docs/api/openapi.yaml';

    public function index(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = $token === null ? [] : array_values(array_filter(ApiAbilities::keys(), fn (string $a) => $token->can($a)));

        return response()->json(['data' => [
            'name' => Branding::name(),
            'version' => BayiYazilimi::VERSION,
            'api_version' => 'v1',
            'user' => ['name' => $request->user()?->name, 'email' => $request->user()?->email],
            'token' => ['name' => $token?->name, 'abilities' => $abilities, 'expires_at' => $token?->expires_at?->toIso8601String()],
            'currency' => Money::baseCode(),
            'documentation' => 'https://api.bayiyazilimi.com',
            'openapi' => route('api.v1.openapi'),
            'mcp' => route('mcp'),
        ]]);
    }

    public function openapi(): BinaryFileResponse
    {
        return response()->file(base_path(self::SPEC), [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
