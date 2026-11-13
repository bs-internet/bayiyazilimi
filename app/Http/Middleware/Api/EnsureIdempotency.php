<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` başlıklı yazma isteği (POST / PUT / PATCH / DELETE) aynı
 * anahtarla 24 saat içinde tekrar gelirse işlem yeniden yapılmaz; ilk yanıt
 * aynen döner (`Idempotent-Replayed: true`). Aynı anahtar başka bir gövdeyle
 * gelirse 422. Anahtarlar API anahtarı başına ayrıdır. Başarısız (5xx) yanıt
 * saklanmaz; istemci aynı anahtarla yeniden deneyebilir.
 */
class EnsureIdempotency
{
    public const TTL = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '' || $request->isMethodSafe()) {
            return $next($request);
        }

        if (strlen($key) > 255) {
            return response()->json(['message' => __('Idempotency-Key en fazla 255 karakter olabilir.')], 422);
        }

        $owner = $request->user()?->currentAccessToken()?->getKey() ?? $request->ip();
        $cacheKey = 'api-idem:'.hash('sha256', $owner.'|'.$key);
        $fingerprint = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());

        $lock = Cache::lock($cacheKey.':lock', 30);

        if (! $lock->get()) {
            return response()->json(['message' => __('Aynı Idempotency-Key ile bir istek hâlâ işleniyor.')], 409);
        }

        try {
            $stored = Cache::get($cacheKey);

            if (is_array($stored)) {
                if ($stored['fingerprint'] !== $fingerprint) {
                    return response()->json(['message' => __('Bu Idempotency-Key başka bir istekle kullanılmış.')], 422);
                }

                return (new JsonResponse(json_decode($stored['body'], true), $stored['status']))
                    ->withHeaders(['Idempotent-Replayed' => 'true']);
            }

            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, ['fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => (string) $response->getContent()], self::TTL);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
