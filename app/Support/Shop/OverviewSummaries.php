<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Http\Controllers\Panel\OverviewController;
use App\Http\Middleware\SiteAccessGate;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Unit;
use App\Services\Shop\ExchangeRateService;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Mağaza ekranlarının bölüm giriş sayfası (Tanımlamalar / Genel Ayarlar) kart özetleri.
 * Anahtarlar menü anahtarlarıdır.
 */
final class OverviewSummaries
{
    /**
     * @return array<string, array{text: string, warning?: bool}>
     */
    public static function definitions(): array
    {
        $accounts = BankAccount::query()->count();
        $activeAccounts = BankAccount::query()->active()->count();
        $transfer = ShopSettings::bool('bank_transfer_enabled');
        $fee = Money::round(ShopSettings::get('shipping_fee'));
        $threshold = Money::round(ShopSettings::get('free_shipping_threshold'));

        return [
            'panel.shop.units.index' => [
                'text' => OverviewController::counts(Unit::query()->count(), Unit::query()->active()->count(), __('birim')),
            ],
            'panel.shop.carriers.index' => [
                'text' => OverviewController::counts(Carrier::query()->count(), Carrier::query()->where('status', 'active')->count(), __('firma')),
            ],
            'panel.shop.shipping-settings.edit' => [
                'text' => ($threshold->isPositive() ? __('Ücretsiz kargo :amount üzeri', ['amount' => self::money($threshold)]) : __('Ücretsiz kargo eşiği yok'))
                    .' · '.($fee->isPositive() ? __('sabit ücret :amount', ['amount' => self::money($fee)]) : __('sabit ücret yok')),
            ],
            'panel.shop.bank-accounts.index' => match (true) {
                $transfer && $activeAccounts === 0 => ['text' => __('Aktif hesap yok; havale / EFT gösterilmiyor'), 'warning' => true],
                default => ['text' => OverviewController::counts($accounts, $activeAccounts, __('hesap')).' · '.($transfer ? __('havale açık') : __('havale kapalı'))],
            },
            'panel.shop.sales-settings.edit' => [
                // Bayinin gördüğü fiyat gösterimi (ziyaretçi / müşteri ayarı vitrinindir).
                'text' => (ShopSettings::get('dealer_price_display') === 'net' ? __('Bayi fiyatları KDV hariç') : __('Bayi fiyatları KDV dahil'))
                    .' · '.__('teklif :quote gün · iade :return gün', ['quote' => ShopSettings::int('quote_valid_days'), 'return' => ShopSettings::int('return_days')]),
            ],
            'panel.shop.exchange-rates.index' => [
                'text' => __(':count aktif · TCMB', ['count' => Currency::query()->where('status', 'active')->count()]).' '
                    .(ExchangeRateService::rateTypes()[ShopSettings::get('tcmb_rate_type')] ?? '')
                    .(ShopSettings::bool('tcmb_auto_update') ? __(', otomatik') : __(', elle')),
            ],
        ];
    }

    /**
     * @return array<string, array{text: string, warning?: bool}>
     */
    public static function general(): array
    {
        $missingTax = ShopSettings::get('tax_number') === '' || ShopSettings::get('tax_office') === '';

        return [
            'panel.shop.corporate.edit' => $missingTax
                ? ['text' => __('Vergi bilgileri eksik'), 'warning' => true]
                : ['text' => (string) ShopSettings::get('store_name')],
            'panel.shop.site-access.edit' => match (true) {
                ShopSettings::bool('maintenance_mode') => ['text' => __('Bakım modu açık; site ziyaretçilere kapalı'), 'warning' => true],
                ! SiteAccessGate::isPublic() => ['text' => __('Yalnız bayilere açık')],
                default => ['text' => __('Herkese açık')],
            },
        ];
    }

    private static function money(BigNumber|int|string $amount): string
    {
        $value = Money::round($amount);

        $whole = $value->isEqualTo($value->toScale(0, RoundingMode::Down));

        return Money::number($value, $whole ? 0 : 2).' ₺';
    }
}
