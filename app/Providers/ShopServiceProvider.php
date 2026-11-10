<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SiteAccessGate;
use App\Models\Account;
use App\Services\Panel\Shop\Reports\ReportCatalog;
use App\Services\Shop\Basket;
use App\Services\Shop\ChequeService;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\PriceCalculator;
use App\Support\Money;
use App\Support\Payments\Gateways\BankTransferGateway;
use App\Support\Payments\Gateways\OnAccountGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Shop\Campaigns\Actions;
use App\Support\Shop\Campaigns\CampaignEngine;
use App\Support\Shop\Campaigns\CampaignRegistry;
use App\Support\Shop\Campaigns\CampaignUsages;
use App\Support\Shop\Campaigns\Conditions;
use App\Support\Shop\OverviewSummaries;
use App\Support\Shop\ShopMenu;
use App\Support\Shop\ShopPermissions;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Mağaza (e-ticaret) katmanı.
 *
 * Çekirdeği düzenlemeden, modül sistemindeki hook'lar üzerinden bağlanır:
 * - `panel.routes.register` / `app.routes.register` → routes/shop/{panel,app}.php
 * - `panel.menu.register` / `app.menu.register`     → "Mağaza" menü grubu (ShopMenu)
 * - `panel.permissions.register`                    → Rotalardan Keşfet + seed (ShopPermissions)
 */
final class ShopServiceProvider extends ServiceProvider
{
    public const SOURCE = 'shop';

    public function register(): void
    {
        $this->app->scoped(CurrencyManager::class);
        $this->app->scoped(PriceCalculator::class);
        // Kampanya koşul / işlem türleri: çekirdektekiler burada, modüllerinkiler
        // 'shop.campaigns.conditions.register' / 'shop.campaigns.actions.register' ile.
        $this->app->singleton(CampaignRegistry::class);
        $this->app->scoped(CampaignEngine::class);
        $this->app->scoped(CampaignUsages::class);
        hooks()->action('shop.campaigns.conditions.register', function (CampaignRegistry $campaigns): void {
            foreach ([
                Conditions\DealerGroupCondition::class,
                Conditions\CustomerCondition::class, Conditions\CartTotalCondition::class,
                Conditions\CartQuantityCondition::class, Conditions\CurrencyCondition::class,
                Conditions\PaymentMethodCondition::class, Conditions\CityCondition::class,
                Conditions\WeekdayCondition::class, Conditions\HourCondition::class,
                Conditions\CategoryCondition::class, Conditions\BrandCondition::class,
                Conditions\ProductCondition::class, Conditions\LineQuantityCondition::class,
            ] as $class) {
                $campaigns->addCondition(new $class);
            }
        });
        hooks()->action('shop.campaigns.actions.register', function (CampaignRegistry $campaigns): void {
            foreach ([
                Actions\PercentDiscountAction::class, Actions\AmountDiscountAction::class,
                Actions\BuyXPayYAction::class, Actions\ShippingAction::class, Actions\GiftProductAction::class,
                Actions\TieredDiscountAction::class,
            ] as $class) {
                $campaigns->addAction(new $class);
            }
        });
        $this->app->bind(Basket::class, fn ($app, array $params) => new Basket(
            $params['name'] ?? 'cart',
            $app->make(PriceCalculator::class),
            $app->make(CurrencyManager::class),
        ));

        hooks()->action('panel.routes.register', function (): void {
            Route::group([], base_path('routes/shop/panel.php'));
        });

        hooks()->action('app.routes.register', function (): void {
            Route::group([], base_path('routes/shop/app.php'));
        });

        // Ödeme sağlayıcısı geri dönüşleri (herkese açık web rotaları).
        hooks()->action('web.routes.register', function (): void {
            Route::group([], base_path('routes/shop/web.php'));
        }, 5);

        // Vadeli risk: portföydeki (tahsil edilmemiş) çek / senetler.
        hooks()->filter('shop.ledger.instrument_risk', fn (BigDecimal $value, Account $account) => Money::add($value, app(ChequeService::class)->risk($account)));

        // Ödeme yöntemleri: çekirdek sürücüler; modüller aynı hook'la ekler.
        $this->app->singleton(PaymentRegistry::class);
        hooks()->action('shop.payment.methods.register', function (PaymentRegistry $payments): void {
            $payments->add(new BankTransferGateway);
            $payments->add(new OnAccountGateway);
        }, 1);

        hooks()->action('panel.menu.register', ShopMenu::panel(...));
        hooks()->action('app.menu.register', ShopMenu::app(...));
        hooks()->action('panel.permissions.register', ShopPermissions::register(...));

        // Çekirdeğin Para Birimleri ekranı yerine kurlarla birleşik ekran.
        hooks()->filter('panel.definitions.currencies.index_url', fn () => route('panel.shop.exchange-rates.index'));

        // Bölüm giriş sayfalarındaki kart özetleri.
        hooks()->filter('panel.definitions.summaries', fn (array $value) => [...$value, ...OverviewSummaries::definitions()]);
        hooks()->filter('panel.settings.general.summaries', fn (array $value) => [...$value, ...OverviewSummaries::general()]);

        // Adlandırma kuralına uymayan yetki bağımlılıkları (bkz. PermissionDependencies).
        hooks()->filter('permissions.dependencies', fn (array $value) => [...$value,
            'panel.shop.products.images.store' => ['panel.shop.products.edit'],
            'panel.shop.products.images.reorder' => ['panel.shop.products.edit'],
            'panel.shop.products.images.destroy' => ['panel.shop.products.edit'],
            'panel.shop.carriers.rates.update' => ['panel.shop.carriers.edit'],
            'panel.shop.carriers.rates.import' => ['panel.shop.carriers.edit'],
            'panel.shop.export.products' => ['panel.shop.products.index'],
            'panel.shop.import.template' => ['panel.shop.import.index'],
            'panel.shop.quotes.products' => ['panel.shop.quotes.edit'],
            'panel.shop.stock-receipts.products' => ['panel.shop.stock-receipts.create'],
            'panel.shop.stock-counts.lookup' => ['panel.shop.stock-counts.lines.store'],
            'panel.shop.quotes.cancel' => ['panel.shop.quotes.edit'],
            'panel.shop.quotes.destroy' => ['panel.shop.quotes.edit'],
            'panel.shop.payments.refund' => ['panel.shop.orders.show'],
            'panel.shop.payments.refunds.confirm' => ['panel.shop.payments.refund'],
            'panel.shop.payments.refunds.abandon' => ['panel.shop.payments.refund'],
            // Rapor Excel'i → o raporu görme (rapor → panel.shop.reports.index kuraldan gelir).
            ...collect(ReportCatalog::keys())->mapWithKeys(fn (string $key) => [
                "panel.shop.reports.{$key}.export" => ["panel.shop.reports.{$key}"],
            ])->all(),
        ]);
    }

    public function boot(): void
    {
        // Site erişimi (bakım modu, herkese açık / yalnız bayi). Router'a değil HTTP
        // Kernel'e eklenir: Kernel grupları istek başında router'a yeniden yazar.
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel) {
            $kernel->appendMiddlewareToGroup('web', SiteAccessGate::class);
        }
    }
}
