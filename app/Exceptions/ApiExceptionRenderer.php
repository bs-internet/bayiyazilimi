<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * REST API (/api/*) hatalarını Türkçe `message` ile JSON'a çevirir. Doğrulama
 * hataları (422) ve diğerleri Laravel'in varsayılan JSON biçiminde kalır;
 * iş kuralı uyarıları (ShopException) kendi render()'ında 422 döner.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof AuthenticationException => self::json(__('Kimlik doğrulanamadı: geçerli bir API anahtarı gönderin (Authorization: Bearer …).'), 401),
            $e instanceof AccessDeniedHttpException => self::json(__('Bu API anahtarının bu işlem için yetkisi yok.'), 403),
            $e instanceof NotFoundHttpException => self::json(__('Kayıt bulunamadı.'), 404),
            $e instanceof MethodNotAllowedHttpException => self::json(__('Bu adres bu HTTP yöntemini desteklemiyor.'), 405),
            $e instanceof ThrottleRequestsException => self::json(__('Çok fazla istek gönderildi; lütfen biraz bekleyip yeniden deneyin.'), 429, $e->getHeaders()),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private static function json(string $message, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, $headers);
    }
}
