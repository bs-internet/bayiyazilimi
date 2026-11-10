<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Tarayıcının bildirdiği CSP ihlallerini günlüğe yazar ("yalnız raporla"
 * kipinde neyin engelleneceği buradan görülür). Tarayıcı eklentilerinin
 * kendi betiklerinden gelen bildirimler atlanır.
 */
class CspReportController extends Controller
{
    private const MAX_BYTES = 16_384;

    private const IGNORED_SCHEMES = ['chrome-extension', 'moz-extension', 'safari-extension', 'safari-web-extension'];

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return response()->noContent();
        }

        $report = json_decode($body, true);
        $report = is_array($report) ? ($report['csp-report'] ?? $report) : null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        $blocked = $this->uri($report['blocked-uri'] ?? null);
        $source = $this->uri($report['source-file'] ?? null);

        foreach ([$blocked, $source] as $uri) {
            if ($uri !== null && in_array(strtolower(explode(':', $uri, 2)[0]), self::IGNORED_SCHEMES, true)) {
                return response()->noContent();
            }
        }

        Log::warning('CSP ihlali', array_filter([
            'page' => $this->uri($report['document-uri'] ?? null),
            'directive' => $report['effective-directive'] ?? $report['violated-directive'] ?? null,
            'blocked' => $blocked,
            'source' => $source,
            'line' => $report['line-number'] ?? null,
            'sample' => isset($report['script-sample']) ? mb_substr((string) $report['script-sample'], 0, 80) : null,
        ], fn ($value) => $value !== null && $value !== ''));

        return response()->noContent();
    }

    /** İmzalı bağlantıların sorgu dizesi (imza, belirteç) günlüğe yazılmaz. */
    private function uri(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr(preg_replace('/[?#].*$/s', '', $value) ?? $value, 0, 300);
    }
}
