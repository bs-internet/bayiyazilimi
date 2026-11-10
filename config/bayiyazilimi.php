<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Kurulum
    |--------------------------------------------------------------------------
    |
    | Kurulum sihirbazı (/kurulum ya da `php artisan bayi:setup`) bitince
    | `.env`'e BAYIYAZILIMI_INSTALLED=true yazılır; kurulu sistemde kurulum
    | rotaları yüklenmez. Değer yoksa veritabanında kullanıcı olup olmadığına
    | bakılır (bkz. App\Support\Install\InstallState).
    |
    */
    // "true" / "1" / "false" / "0" hepsi bool'a çevrilir (PHPUnit <env value="true">
    // değeri ortama "1" olarak verir); değer yoksa null kalır.
    'installed' => env('BAYIYAZILIMI_INSTALLED') === null
        ? null
        : filter_var(env('BAYIYAZILIMI_INSTALLED'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),

    /*
    |--------------------------------------------------------------------------
    | Modül Vendor'ları
    |--------------------------------------------------------------------------
    |
    | Modül paketlerinin composer vendor adları: `bsinternet` (Bayi Yazılımı
    | modülleri: vitrin, plasiyer, ödeme, kargo, SMS, entegrasyonlar). Modül
    | sayfaları host'a kopyalanmaz; her vendor klasöründeki paketlerin
    | `resources/js/pages` klasöründen çözülür.
    |
    | Yeni bir vendor eklenirse aynı liste statik olarak şu yerlerde de
    | güncellenmelidir (derleme araçları config okuyamaz):
    | resources/js/inertia-config.tsx, resources/js/extensions.ts,
    | resources/js/lib/i18n.ts (import.meta.glob), tsconfig.json (include)
    | ve resources/css/app.css (@source).
    |
    */
    'module_vendors' => ['bsinternet'],

    /*
    |--------------------------------------------------------------------------
    | Modül Keşif Yolları
    |--------------------------------------------------------------------------
    |
    | Modüller composer ile yüklenip Laravel auto-discovery tarafından boot
    | edilir. Bu yollar yalnızca `bayi:install` / `bayi:uninstall`
    | komutlarının ve "Modüller" ekranının module.json dosyalarını
    | bulabilmesi içindir (salt-okur metadata).
    |
    */
    'module_discovery_paths' => [
        base_path('vendor/bsinternet/*/module.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modül Durumu
    |--------------------------------------------------------------------------
    |
    | Panelden kapatılan modüller bu dosyada tutulur (storage/ altına göre).
    | Kapalı modülün paketi Laravel auto-discovery'de atlanır: provider'ı hiç
    | yüklenmez, rotası / menüsü / kancası / çevirisi devreye girmez. Verisi
    | silinmez; yeniden açınca kaldığı yerden devam eder. Veritabanı yerine
    | dosya kullanılır çünkü provider'lar veritabanından önce yüklenir.
    |
    */
    'module_state' => storage_path(env('BAYIYAZILIMI_MODULE_STATE', 'app/modules.json')),

    /*
    |--------------------------------------------------------------------------
    | REST API İstek Sınırı
    |--------------------------------------------------------------------------
    |
    | /api/v1 uç noktalarında API anahtarı başına dakikada en çok kaç istek
    | yapılabileceği (`throttle:api` sınırlayıcısı, bkz. AppServiceProvider).
    |
    */
    'api_rate_limit' => (int) env('BAYIYAZILIMI_API_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Güvenilen Proxy'ler
    |--------------------------------------------------------------------------
    |
    | Sunucu proxy / CDN arkasındaysa: virgülle ayrılmış IP / CIDR listesi ya da
    | yalnız proxy'den erişilebilen sunucuda `*`. Boşsa bağlantının adresi kullanılır.
    |
    */
    'trusted_proxies' => env('BAYIYAZILIMI_TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | İçerik Güvenlik Politikası (CSP)
    |--------------------------------------------------------------------------
    |
    | report: tarayıcı kuralı uygulamaz, ihlalleri bildirir (günlüğe "CSP ihlali"
    | olarak yazılır) · enforce: kurala uymayan betik / çerçeve engellenir ·
    | off: başlık gönderilmez. Günlük bir süre temiz kalınca enforce'a geçin.
    |
    */
    'csp' => env('BAYIYAZILIMI_CSP', 'report'),
];
