<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Güvenilen proxy'ler (BAYIYAZILIMI_TRUSTED_PROXIES). Gerçek istemci IP'si ve https bilgisi
 * yalnız bu adreslerden gelen X-Forwarded-* başlıklarından okunur.
 *
 * Değer virgülle ayrılmış liste: `cloudflare` Cloudflare'in yayımladığı aralıkları, `*` her
 * proxy'yi (yalnız sunucuya proxy dışından erişilemiyorsa), diğerleri IP / CIDR olarak eklenir.
 */
final class TrustedProxies
{
    /** https://www.cloudflare.com/ips/ */
    public const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** @return list<string>|string|null */
    public static function resolve(?string $value): array|string|null
    {
        $items = array_values(array_filter(array_map(trim(...), explode(',', (string) $value)), fn (string $item): bool => $item !== ''));

        if ($items === []) {
            return null;
        }

        if (in_array('*', $items, true)) {
            return '*';
        }

        $proxies = [];

        foreach ($items as $item) {
            array_push($proxies, ...(strtolower($item) === 'cloudflare' ? self::CLOUDFLARE : [$item]));
        }

        return array_values(array_unique($proxies));
    }
}
