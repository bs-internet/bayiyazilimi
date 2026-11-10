<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * İçerik güvenlik politikası (CSP). Betikler yalnız istek başına üretilen
 * nonce ile çalışır ('strict-dynamic': nonce'lu betiğin yüklediği betikler de
 * güvenilir); satır içi olay öznitelikleri (onerror= …) ve sayfaya sızan
 * yabancı betikler engellenir. Dış kaynak kullanan eklenti adreslerini
 * `security.csp` filtresiyle ekler:
 *
 *     hooks()->filter('security.csp', fn (array $value) => array_merge_recursive($value, [
 *         'frame-src' => ['https://www.paytr.com'],
 *     ]));
 *
 * Kip `BAYIYAZILIMI_CSP`: report (varsayılan; tarayıcı yalnız bildirir),
 * enforce (engeller) ya da off.
 */
final class ContentSecurityPolicy
{
    public const OFF = 'off';

    public const REPORT = 'report';

    public const ENFORCE = 'enforce';

    public static function mode(): string
    {
        $mode = strtolower((string) config('bayiyazilimi.csp', self::REPORT));

        return in_array($mode, [self::OFF, self::REPORT, self::ENFORCE], true) ? $mode : self::REPORT;
    }

    public static function headerName(): ?string
    {
        return match (self::mode()) {
            self::ENFORCE => 'Content-Security-Policy',
            self::REPORT => 'Content-Security-Policy-Report-Only',
            default => null,
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function directives(string $nonce): array
    {
        $directives = [
            'default-src' => ["'self'"],
            // 'self' https: 'unsafe-inline' yalnız 'strict-dynamic' bilmeyen eski
            // tarayıcılar içindir; yenileri nonce'u görünce bunları yok sayar.
            'script-src' => ["'nonce-{$nonce}'", "'strict-dynamic'", "'self'", 'https:', "'unsafe-inline'"],
            // React style özniteliği ve bileşenlerin eklediği <style> etiketleri.
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        /** @var array<string, list<string>> $directives */
        $directives = hooks()->apply('security.csp', $directives);

        return $directives;
    }

    public static function header(string $nonce, ?string $reportUri = null): string
    {
        $parts = [];

        foreach (self::directives($nonce) as $directive => $sources) {
            $sources = array_unique(array_filter((array) $sources, fn ($source) => is_string($source) && $source !== ''));
            $parts[] = trim($directive.' '.implode(' ', $sources));
        }

        if ($reportUri !== null) {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }
}
