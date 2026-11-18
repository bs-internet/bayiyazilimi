<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Events\System\ErrorReportedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hata bildirimi kapısı: raporlanan hata (bootstrap/app.php → withExceptions)
 * günlüğe yazılmaya devam eder; buradan ayrıca ErrorReportedEvent yayımlanır,
 * listener yöneticilere e-posta + panel bildirimi gönderir.
 *
 * - Yalnız açıkken (`bayiyazilimi.error_alerts.enabled`; boşsa yalnız canlıda).
 * - Aynı hata (tür + dosya + satır) `throttle` dakikada bir kez; toplamda saatte
 *   en çok MAX_PER_HOUR bildirim (hata fırtınası posta kutusunu doldurmasın).
 * - Bildirim gönderirken oluşan hata yeniden bildirilmez ve isteği bozmaz.
 */
final class ErrorAlerts
{
    public const MAX_PER_HOUR = 20;

    private static bool $sending = false;

    public static function enabled(): bool
    {
        $enabled = config('bayiyazilimi.error_alerts.enabled');

        return is_bool($enabled) ? $enabled : app()->isProduction();
    }

    public static function capture(Throwable $e): void
    {
        if (self::$sending || ! self::enabled()) {
            return;
        }

        self::$sending = true;

        try {
            $signature = 'error-alert:'.sha1($e::class.'|'.$e->getFile().'|'.$e->getLine());
            $minutes = max(1, (int) config('bayiyazilimi.error_alerts.throttle', 60));

            if (! Cache::add($signature, true, now()->addMinutes($minutes))) {
                return;
            }

            if (RateLimiter::tooManyAttempts('error-alerts', self::MAX_PER_HOUR)) {
                return;
            }

            RateLimiter::hit('error-alerts', 3600);

            event(new ErrorReportedEvent(
                type: $e::class,
                message: Str::limit($e->getMessage(), 1000),
                location: self::location($e),
                url: self::url(),
                occurredAt: CarbonImmutable::now(),
            ));
        } catch (Throwable) {
            // Bildirim gönderilemedi (posta / veritabanı erişilemiyor): hata zaten günlükte.
        } finally {
            self::$sending = false;
        }
    }

    private static function location(Throwable $e): string
    {
        return Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$e->getLine();
    }

    /** İsteğin adresi; sorgu dizesi (imza, token) yazılmaz. Konsolda null. */
    private static function url(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $request = request();

        return $request->method().' '.$request->url();
    }
}
