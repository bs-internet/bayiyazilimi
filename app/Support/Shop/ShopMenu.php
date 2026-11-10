<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Enums\Shop\CustomerType;
use App\Services\Shop\ShopSettings;
use App\Support\Registry\MenuRegistry;

/**
 * Mağaza katmanının menü katkıları (ShopServiceProvider hook'ları çağırır):
 * panelde "Mağaza" grubu ve çekirdek öğelerin yeniden tanımı, üye alanında
 * alışveriş / cari / iade öğeleri.
 */
final class ShopMenu
{
    public static function panel(MenuRegistry $menu): void
    {
        // Çekirdeğin "Üyeler" öğesi aynı anahtarla yeniden tanımlanır: B2B'de
        // "Bayiler"; müşteri hesapları varken (vitrin) "Müşteriler" (müşteri + bayi).
        $members = CustomerType::customersEnabled() ? __('Müşteriler') : __('Bayiler');

        $menu->add('panel', 'platform', [
            'key' => 'panel.members',
            'title' => $members,
            'href' => '#',
            'icon' => 'users',
            'permission' => 'panel.members.index',
            'order' => 20,
            'items' => [
                ['key' => 'panel.members.index', 'title' => $members, 'href' => route('panel.members.index'), 'permission' => 'panel.members.index', 'order' => 10],
                ['key' => 'panel.shop.ledger.index', 'title' => __('Cari Durum'), 'href' => route('panel.shop.ledger.index'), 'permission' => 'panel.shop.ledger.index', 'order' => 15],
                ['key' => 'panel.members.create', 'title' => __('Yeni Bayi'), 'href' => route('panel.members.create'), 'permission' => 'panel.members.create', 'order' => 20],
                ['key' => 'panel.shop.dealer-groups.index', 'title' => __('Bayi Grupları'), 'href' => route('panel.shop.dealer-groups.index'), 'permission' => 'panel.shop.dealer-groups.index', 'order' => 30],
            ],
        ]);

        // Müşterilerin altında.
        $menu->add('panel', 'platform', [
            'key' => 'panel.shop.campaigns.index',
            'title' => __('Kampanyalar'),
            'href' => route('panel.shop.campaigns.index'),
            'icon' => 'tag',
            'permission' => 'panel.shop.campaigns.index',
            'order' => 22,
            'collapsible' => false,
            'items' => [
                ['key' => 'panel.shop.campaigns.list', 'title' => __('Kampanyalar'), 'href' => route('panel.shop.campaigns.index'), 'permission' => 'panel.shop.campaigns.index', 'order' => 10],
                ['key' => 'panel.shop.campaigns.create', 'title' => __('Yeni Kampanya'), 'href' => route('panel.shop.campaigns.create'), 'permission' => 'panel.shop.campaigns.create', 'order' => 20],
            ],
        ]);

        // Kampanyalar'ın altında; raporlar bölümün iç menüsünde (yetkiye göre).
        $menu->add('panel', 'platform', [
            'key' => 'panel.shop.reports.index',
            'title' => __('Raporlar'),
            'href' => route('panel.shop.reports.index'),
            'icon' => 'chartColumn',
            'permission' => 'panel.shop.reports.index',
            'order' => 23,
            'collapsible' => false,
        ]);

        $menu->group('panel', 'shop', __('Mağaza'), 15);

        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.orders',
            'title' => __('Siparişler'),
            'href' => route('panel.shop.orders.index'),
            'icon' => 'shoppingCart',
            'permission' => 'panel.shop.orders.index',
            'order' => 10,
        ]);

        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.quotes',
            'title' => __('Teklifler'),
            'href' => route('panel.shop.quotes.index'),
            'icon' => 'fileText',
            'permission' => 'panel.shop.quotes.index',
            'order' => 20,
        ]);

        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.returns',
            'title' => __('İadeler'),
            'href' => route('panel.shop.returns.index'),
            'icon' => 'undo2',
            'permission' => 'panel.shop.returns.index',
            'order' => 25,
        ]);

        // Ödemeler: tahsilatlar, havale bildirimleri, çek / senet, geciken ödemeler.
        // Müşteri bakiyeleri (Cari Durum) Müşteriler menüsünün altında.
        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.ledger',
            'title' => __('Ödemeler'),
            // İlk tıklamada Tahsilatlar; alt öğeler açılır menü değil, bölümün iç menüsü.
            'href' => route('panel.shop.ledger.collections'),
            'icon' => 'wallet',
            'permission' => 'panel.shop.ledger.collections',
            'order' => 27,
            'collapsible' => false,
            'items' => [
                ['key' => 'panel.shop.ledger.collections', 'title' => __('Tahsilatlar'), 'href' => route('panel.shop.ledger.collections'), 'permission' => 'panel.shop.ledger.collections', 'order' => 10],
                ['key' => 'panel.shop.payment-notifications.index', 'title' => __('Havale Bildirimleri'), 'href' => route('panel.shop.payment-notifications.index'), 'permission' => 'panel.shop.payment-notifications.index', 'order' => 20],
                // Çek / senet isteğe bağlı: Cari Ayarları'nda kapalıyken menüde yer almaz.
                ...(ShopSettings::bool('cheques_enabled') ? [['key' => 'panel.shop.cheques.index', 'title' => __('Çek / Senet'), 'href' => route('panel.shop.cheques.index'), 'permission' => 'panel.shop.cheques.index', 'order' => 30]] : []),
                ['key' => 'panel.shop.ledger.aging', 'title' => __('Geciken Ödemeler'), 'href' => route('panel.shop.ledger.aging'), 'permission' => 'panel.shop.ledger.aging', 'order' => 40],
            ],
        ]);

        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.catalog',
            'title' => __('Katalog'),
            'href' => '#',
            'icon' => 'package',
            'permission' => 'panel.shop.products.index',
            'order' => 30,
            'items' => [
                ['key' => 'panel.shop.products.index', 'title' => __('Ürünler'), 'href' => route('panel.shop.products.index'), 'permission' => 'panel.shop.products.index', 'order' => 10],
                ['key' => 'panel.shop.products.create', 'title' => __('Yeni Ürün'), 'href' => route('panel.shop.products.create'), 'permission' => 'panel.shop.products.create', 'order' => 20],
                ['key' => 'panel.shop.categories.index', 'title' => __('Kategoriler'), 'href' => route('panel.shop.categories.index'), 'permission' => 'panel.shop.categories.index', 'order' => 30],
                ['key' => 'panel.shop.brands.index', 'title' => __('Markalar'), 'href' => route('panel.shop.brands.index'), 'permission' => 'panel.shop.brands.index', 'order' => 40],
                ['key' => 'panel.shop.import.index', 'title' => __('İçe / Dışa Aktar'), 'href' => route('panel.shop.import.index'), 'permission' => 'panel.shop.import.index', 'order' => 50],
            ],
        ]);

        $menu->add('panel', 'shop', [
            'key' => 'panel.shop.inventory',
            'title' => __('Stok'),
            'href' => '#',
            'icon' => 'warehouse',
            'permission' => 'panel.shop.stock.index',
            'order' => 40,
            'items' => [
                ['key' => 'panel.shop.stock.index', 'title' => __('Stok Durumu'), 'href' => route('panel.shop.stock.index'), 'permission' => 'panel.shop.stock.index', 'order' => 10],
                ['key' => 'panel.shop.orders.pick-list', 'title' => __('Toplama Listesi'), 'href' => route('panel.shop.orders.pick-list'), 'permission' => 'panel.shop.orders.pick-list', 'order' => 12],
                ['key' => 'panel.shop.stock-receipts.index', 'title' => __('Mal Kabul'), 'href' => route('panel.shop.stock-receipts.index'), 'permission' => 'panel.shop.stock-receipts.index', 'order' => 15],
                ['key' => 'panel.shop.stock-counts.index', 'title' => __('Sayım'), 'href' => route('panel.shop.stock-counts.index'), 'permission' => 'panel.shop.stock-counts.index', 'order' => 18],
                ['key' => 'panel.shop.stock.movements', 'title' => __('Stok Hareketleri'), 'href' => route('panel.shop.stock.movements'), 'permission' => 'panel.shop.stock.movements', 'order' => 20],
                ...(ShopSettings::traceability() ? [['key' => 'panel.shop.stock.lots', 'title' => __('Lot / Seri No'), 'href' => route('panel.shop.stock.lots'), 'permission' => 'panel.shop.stock.lots', 'order' => 25]] : []),
                ['key' => 'panel.shop.warehouses.index', 'title' => __('Depolar'), 'href' => route('panel.shop.warehouses.index'), 'permission' => 'panel.shop.warehouses.index', 'order' => 30],
                ['key' => 'panel.shop.stock-settings.edit', 'title' => __('Stok Ayarları'), 'href' => route('panel.shop.stock-settings.edit'), 'permission' => 'panel.shop.stock-settings.edit', 'order' => 40],
            ],
        ]);

        // Tanımlamalar: çekirdeğin "Para Birimleri" ekranı yerine kurlarla
        // birleşik mağaza ekranı; kargo firmaları da burada.
        $menu->removeChild('panel', 'settings', 'panel.tools.definitions', 'panel.tools.definitions.currencies.index');
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.exchange-rates.index',
            'title' => __('Para Birimleri'),
            'href' => route('panel.shop.exchange-rates.index'),
            'icon' => 'coins',
            'description' => __('Para birimleri, kurlar ve TCMB ayarları.'),
            'permission' => 'panel.shop.exchange-rates.index',
            'order' => 30,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.units.index',
            'title' => __('Birimler'),
            'href' => route('panel.shop.units.index'),
            'icon' => 'ruler',
            'description' => __('Ürün birimleri (Adet, Kutu, Litre …).'),
            'permission' => 'panel.shop.units.index',
            'order' => 45,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.suppliers.index',
            'title' => __('Tedarikçiler'),
            'href' => route('panel.shop.suppliers.index'),
            'icon' => 'factory',
            'description' => __('Mal kabulde seçilen tedarikçiler.'),
            'permission' => 'panel.shop.suppliers.index',
            'order' => 47,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.carriers.index',
            'title' => __('Kargo Firmaları'),
            'href' => route('panel.shop.carriers.index'),
            'icon' => 'truck',
            'description' => __('Kargo firmaları ve desi fiyat tabloları.'),
            'permission' => 'panel.shop.carriers.index',
            'order' => 50,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.shipping-settings.edit',
            'title' => __('Kargo Ayarları'),
            'href' => route('panel.shop.shipping-settings.edit'),
            'icon' => 'package',
            'description' => __('Sabit kargo ücreti, ücretsiz kargo eşiği, varsayılan desi.'),
            'permission' => 'panel.shop.shipping-settings.edit',
            'order' => 52,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.sales-settings.edit',
            'title' => __('Satış Ayarları'),
            'href' => route('panel.shop.sales-settings.edit'),
            'icon' => 'tag',
            'description' => __('Fiyat gösterimi (KDV dahil / hariç), teklif geçerlilik ve iade süreleri.'),
            'permission' => 'panel.shop.sales-settings.edit',
            'order' => 53,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.ledger-settings.edit',
            'title' => __('Cari Ayarları'),
            'href' => route('panel.shop.ledger-settings.edit'),
            'icon' => 'wallet',
            'description' => __('Ödeme yöntemlerinin en düşük tutarı, vade uyarısı, çek / senet.'),
            'permission' => 'panel.shop.ledger-settings.edit',
            'order' => 56,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.bank-accounts.index',
            'title' => __('Banka Hesapları'),
            'href' => route('panel.shop.bank-accounts.index'),
            'icon' => 'landmark',
            'description' => __('Havale / EFT hesapları ve ödeme seçeneği.'),
            'permission' => 'panel.shop.bank-accounts.index',
            'order' => 55,
        ]);
        $menu->addChild('panel', 'settings', 'panel.tools.definitions', [
            'key' => 'panel.shop.contracts.index',
            'title' => __('Sözleşmeler'),
            'href' => route('panel.shop.contracts.index'),
            'icon' => 'fileText',
            'description' => __('Ödeme ve üyelik formunda onaylatılan metinler.'),
            'permission' => 'panel.shop.contracts.index',
            'order' => 58,
        ]);
        // Genel Ayarlar bölümüne Kurumsal Bilgiler (firma, iletişim, vergi) ve
        // İletişim Bilgileri (iletişim sayfası, sosyal medya) ve Site Erişimi (bakım modu).
        $menu->addChild('panel', 'settings', 'panel.settings.general', [
            'key' => 'panel.shop.corporate.edit',
            'title' => __('Kurumsal Bilgiler'),
            'href' => route('panel.shop.corporate.edit'),
            'icon' => 'building',
            'description' => __('Firma ünvanı, vergi / MERSİS ve adres.'),
            'permission' => 'panel.shop.corporate.edit',
            'order' => 20,
        ]);
        $menu->addChild('panel', 'settings', 'panel.settings.general', [
            'key' => 'panel.shop.site-access.edit',
            'title' => __('Site Erişimi'),
            'href' => route('panel.shop.site-access.edit'),
            'icon' => 'shieldCheck',
            'description' => __('Bakım modu ve bakım sayfası mesajı.'),
            'permission' => 'panel.shop.site-access.edit',
            'order' => 40,
        ]);
    }

    public static function app(MenuRegistry $menu): void
    {
        // Çekirdeğin "Hesabım" öğesi sol menüden kaldırılır: Hesabım / Adreslerim
        // üst çubuktaki kullanıcı menüsünden açılır (sayfalarda kendi alt menüleri var).
        $menu->remove('app', 'platform', 'app.account');

        $items = [
            // Bayi alışverişi /app içinde: Sipariş ver (ürün arama + sepet), teklifler, kayıtlı sepetler.
            ['app.shop.cart.index', __('Sipariş ver'), 'shoppingBasket', 24],
            ['app.shop.saved-carts.index', __('Kayıtlı Sepetler'), 'archive', 28],
            ['app.shop.orders.index', __('Siparişlerim'), 'shoppingCart', 30],
            ['app.shop.payments.index', __('Cari Hesabım'), 'wallet', 50],
            ['app.shop.returns.index', __('İadelerim'), 'undo2', 60],
            ['app.shop.contracts.index', __('Sözleşmeler'), 'fileText', 80],
        ];

        foreach ($items as [$name, $title, $icon, $order]) {
            $menu->add('app', 'platform', [
                'key' => $name,
                'title' => $title,
                'href' => route($name),
                'icon' => $icon,
                'order' => $order,
            ]);
        }

        // Teklifler: teklif listesi + tekliflerim tek bölüm (iç menü sayfada).
        $menu->add('app', 'platform', [
            'key' => 'app.shop.quotes',
            'title' => __('Teklifler'),
            'href' => route('app.shop.quotes.index'),
            'icon' => 'fileText',
            'order' => 26,
            'collapsible' => false,
            'items' => [
                ['key' => 'app.shop.quote-list.index', 'title' => __('Teklif listem'), 'href' => route('app.shop.quote-list.index'), 'order' => 10],
                ['key' => 'app.shop.quotes.index', 'title' => __('Tekliflerim'), 'href' => route('app.shop.quotes.index'), 'order' => 20],
            ],
        ]);
    }
}
