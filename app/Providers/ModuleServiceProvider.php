<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Hooks\HookManager;
use App\Support\Modules\ModuleDiscovery;
use App\Support\Registry\MenuRegistry;
use App\Support\Registry\PermissionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Modül altyapısının çekirdek sağlayıcısı.
 *
 * - Hook yöneticisini ve registry'leri singleton olarak bağlar.
 * - Çekirdeğin kendi menüsünü, tıpkı bir modül gibi, `{area}.menu.register`
 *   hook'una bağlayarak kaydeder (modüllere örnek teşkil eder).
 *
 * Modüller kendi provider'larında aynı hook'lara bağlanarak menü/yetki/rota
 * ekler. Hook callback'leri `register()` içinde kaydedilmelidir; böylece
 * çekirdek tetiklemeden (örn. rota yüklemesi) önce hazır olurlar.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Paket (modül) provider'ları çekirdek provider'lardan ÖNCE register
        // edilir ve hooks() ile singleton'ı zaten kurmuş olabilir; ezilmesin.
        $this->app->singletonIf(HookManager::class);
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(ModuleDiscovery::class);

        hooks()->action('panel.menu.register', $this->registerPanelMenu(...));
        hooks()->action('app.menu.register', $this->registerAppMenu(...));
    }

    public function registerPanelMenu(MenuRegistry $menu): void
    {
        $menu->group('panel', 'platform', __('Platform'), 10);
        $menu->group('panel', 'settings', __('Ayarlar'), 30);

        $menu->add('panel', 'platform', [
            'key' => 'panel.dashboard',
            'title' => __('Başlangıç'),
            'href' => route('panel.dashboard'),
            'icon' => 'layoutGrid',
            'order' => 10,
        ]);

        $menu->add('panel', 'platform', [
            'key' => 'panel.members',
            'title' => __('Üyeler'),
            'href' => '#',
            'icon' => 'users',
            'permission' => 'panel.members.index',
            'order' => 20,
            'items' => [
                [
                    'key' => 'panel.members.index',
                    'title' => __('Üyeler'),
                    'href' => route('panel.members.index'),
                    'permission' => 'panel.members.index',
                    'order' => 10,
                ],
                [
                    'key' => 'panel.members.create',
                    'title' => __('Yeni Üye'),
                    'href' => route('panel.members.create'),
                    'permission' => 'panel.members.create',
                    'order' => 20,
                ],
            ],
        ]);

        $menu->add('panel', 'settings', [
            'key' => 'panel.tools.activity',
            'title' => __('Etkinlik Kayıtları'),
            'href' => route('panel.tools.activity'),
            'icon' => 'activity',
            'permission' => 'panel.tools.activity',
            'order' => 40,
        ]);

        $menu->add('panel', 'settings', [
            'key' => 'panel.tools.cache',
            'title' => __('Ön Bellek Yönetimi'),
            'href' => route('panel.tools.cache'),
            'icon' => 'scanText',
            'permission' => 'panel.tools.cache',
            'order' => 50,
        ]);

        // Alt öğeler sidebar'da açılır menü olmaz; bölüm sayfasının iç menüsünü
        // oluşturur (bkz. layouts/panel/tools/definitions).
        $menu->add('panel', 'settings', [
            'key' => 'panel.tools.definitions',
            'title' => __('Tanımlamalar'),
            'href' => route('panel.tools.definitions.index'),
            'icon' => 'bookMarked',
            'permission' => 'panel.tools.definitions.languages.index',
            'order' => 30,
            'collapsible' => false,
            'items' => [
                [
                    'key' => 'panel.tools.definitions.index',
                    'title' => __('Genel Bakış'),
                    'href' => route('panel.tools.definitions.index'),
                    'icon' => 'layoutGrid',
                    'order' => 0,
                ],
                [
                    'key' => 'panel.tools.definitions.languages.index',
                    'title' => __('Diller'),
                    'href' => route('panel.tools.definitions.languages.index'),
                    'icon' => 'languages',
                    'description' => __('Sistemde kullanılan diller ve varsayılan dil.'),
                    'permission' => 'panel.tools.definitions.languages.index',
                    'order' => 10,
                ],
                [
                    'key' => 'panel.tools.definitions.countries.index',
                    'title' => __('Bölgeler'),
                    'href' => route('panel.tools.definitions.countries.index'),
                    'icon' => 'mapPin',
                    'description' => __('Ülke, il ve ilçe listeleri; adres formlarında kullanılır.'),
                    'permission' => 'panel.tools.definitions.countries.index',
                    'order' => 20,
                ],
                [
                    'key' => 'panel.tools.definitions.currencies.index',
                    'title' => __('Para Birimleri'),
                    'href' => route('panel.tools.definitions.currencies.index'),
                    'icon' => 'coins',
                    'description' => __('Para birimleri ve biçimleri.'),
                    'permission' => 'panel.tools.definitions.currencies.index',
                    'order' => 30,
                ],
                [
                    'key' => 'panel.tools.definitions.taxes.index',
                    'title' => __('Vergi Oranları'),
                    'href' => route('panel.tools.definitions.taxes.index'),
                    'icon' => 'percent',
                    'description' => __('Ürünlerde seçilen KDV oranları.'),
                    'permission' => 'panel.tools.definitions.taxes.index',
                    'order' => 40,
                ],
            ],
        ]);

        $menu->add('panel', 'settings', [
            'key' => 'panel.settings.general',
            'title' => __('Genel Ayarlar'),
            'href' => route('panel.settings.general.overview'),
            'icon' => 'bolt',
            'permission' => 'panel.settings.general.edit',
            'order' => 10,
            // Alt öğeler (modüllerin eklediği ayar sayfaları) bölüm sayfasının iç menüsündedir.
            'collapsible' => false,
            'items' => [
                [
                    'key' => 'panel.settings.general.overview',
                    'title' => __('Genel Bakış'),
                    'href' => route('panel.settings.general.overview'),
                    'icon' => 'layoutGrid',
                    'order' => 0,
                ],
                [
                    'key' => 'panel.settings.general.edit',
                    'title' => __('Genel Ayarlar'),
                    'href' => route('panel.settings.general.edit'),
                    'icon' => 'slidersHorizontal',
                    'description' => __('Panel adı, logo ve varsayılan ülke, para birimi, dil, saat dilimi.'),
                    'permission' => 'panel.settings.general.edit',
                    'order' => 10,
                ],
            ],
        ]);

        // Eklentiler bölümü: kurulu eklentiler (aç / kapat, tüm eklentiler listesi);
        // temalar aynı bölümün iç menüsüne eklenir.
        $menu->add('panel', 'settings', [
            'key' => 'panel.settings.extensions',
            'title' => __('Eklentiler'),
            'href' => route('panel.settings.extensions.index'),
            'icon' => 'blocks',
            'permission' => 'panel.settings.extensions.index',
            'order' => 60,
            'collapsible' => false,
            'items' => [
                [
                    'key' => 'panel.settings.extensions.index',
                    'title' => __('Eklentiler'),
                    'href' => route('panel.settings.extensions.index'),
                    'icon' => 'blocks',
                    'description' => __('Kurulu eklentileri açın / kapatın, diğer eklentileri inceleyin.'),
                    'permission' => 'panel.settings.extensions.index',
                    'order' => 10,
                ],
                [
                    'key' => 'panel.settings.themes.index',
                    'title' => __('Temalar'),
                    'href' => route('panel.settings.themes.index'),
                    'icon' => 'palette',
                    'description' => __('Mağaza ön yüzünün temasını seçin.'),
                    'permission' => 'panel.settings.themes.index',
                    'order' => 20,
                ],
            ],
        ]);

        // API Anahtarları: REST API (/api/v1) anahtarları; ERP / muhasebe entegrasyonları.
        $menu->add('panel', 'settings', [
            'key' => 'panel.settings.api-tokens',
            'title' => __('API Anahtarları'),
            'href' => route('panel.settings.api-tokens.index'),
            'icon' => 'keyRound',
            'permission' => 'panel.settings.api-tokens.index',
            'order' => 70,
            'collapsible' => false,
            'items' => [
                [
                    'key' => 'panel.settings.api-tokens.index',
                    'title' => __('API Anahtarları'),
                    'href' => route('panel.settings.api-tokens.index'),
                    'icon' => 'keyRound',
                    'description' => __('ERP, muhasebe ve özel entegrasyonlar için REST API anahtarları.'),
                    'permission' => 'panel.settings.api-tokens.index',
                    'order' => 10,
                ],
                [
                    'key' => 'panel.settings.webhooks.index',
                    'title' => __('Webhook\'lar'),
                    'href' => route('panel.settings.webhooks.index'),
                    'icon' => 'webhook',
                    'description' => __('Sipariş, ödeme, cari ve stok olaylarında dış sistemlere imzalı bildirim.'),
                    'permission' => 'panel.settings.webhooks.index',
                    'order' => 20,
                ],
            ],
        ]);

        $menu->add('panel', 'settings', [
            'key' => 'panel.settings.users',
            'title' => __('Kullanıcılar'),
            'href' => route('panel.settings.users.index'),
            'icon' => 'squareUser',
            'permission' => 'panel.settings.users.index',
            'order' => 20,
            // Alt öğeler (kullanıcılar, roller, yetkiler) bölüm sayfasının iç menüsündedir.
            'collapsible' => false,
            'items' => [
                [
                    'key' => 'panel.settings.users.index',
                    'title' => __('Kullanıcılar'),
                    'href' => route('panel.settings.users.index'),
                    'permission' => 'panel.settings.users.index',
                    'order' => 10,
                ],
                [
                    'key' => 'panel.settings.users.create',
                    'title' => __('Yeni Kullanıcı'),
                    'href' => route('panel.settings.users.create'),
                    'permission' => 'panel.settings.users.create',
                    'order' => 20,
                ],
                [
                    'key' => 'panel.settings.roles.index',
                    'title' => __('Görevler'),
                    'href' => route('panel.settings.roles.index'),
                    'permission' => 'panel.settings.roles.index',
                    'order' => 30,
                ],
                [
                    'key' => 'panel.settings.permissions.index',
                    'title' => __('Yetkiler'),
                    'href' => route('panel.settings.permissions.index'),
                    'permission' => 'panel.settings.permissions.index',
                    'order' => 40,
                ],
            ],
        ]);
    }

    public function registerAppMenu(MenuRegistry $menu): void
    {
        $menu->group('app', 'platform', __('Platform'), 10);

        $menu->add('app', 'platform', [
            'key' => 'app.dashboard',
            'title' => __('Başlangıç'),
            'href' => route('app.dashboard'),
            'icon' => 'layoutGrid',
            'order' => 10,
        ]);

        $menu->add('app', 'platform', [
            'key' => 'app.account',
            'title' => __('Hesabım'),
            'href' => '#',
            'icon' => 'userRound',
            'order' => 20,
            'items' => [
                [
                    'key' => 'app.account',
                    'title' => __('Hesabım'),
                    'href' => route('app.account'),
                    'order' => 10,
                ],
            ],
        ]);
    }
}
