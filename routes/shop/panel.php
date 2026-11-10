<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\Shop\AccountLedgerController;
use App\Http\Controllers\Panel\Shop\BankAccountController;
use App\Http\Controllers\Panel\Shop\BrandController;
use App\Http\Controllers\Panel\Shop\CampaignController;
use App\Http\Controllers\Panel\Shop\CarrierController;
use App\Http\Controllers\Panel\Shop\CategoryController;
use App\Http\Controllers\Panel\Shop\ChequeController;
use App\Http\Controllers\Panel\Shop\ContractController;
use App\Http\Controllers\Panel\Shop\CorporateInfoController;
use App\Http\Controllers\Panel\Shop\CustomerTypeController;
use App\Http\Controllers\Panel\Shop\DealerGroupController;
use App\Http\Controllers\Panel\Shop\ExchangeRateController;
use App\Http\Controllers\Panel\Shop\ExportController;
use App\Http\Controllers\Panel\Shop\ImportController;
use App\Http\Controllers\Panel\Shop\LedgerSettingsController;
use App\Http\Controllers\Panel\Shop\OrderController;
use App\Http\Controllers\Panel\Shop\PaymentNotificationController;
use App\Http\Controllers\Panel\Shop\PaymentRefundController;
use App\Http\Controllers\Panel\Shop\ProductController;
use App\Http\Controllers\Panel\Shop\QuoteController;
use App\Http\Controllers\Panel\Shop\ReportController;
use App\Http\Controllers\Panel\Shop\ReturnController;
use App\Http\Controllers\Panel\Shop\SalesSettingsController;
use App\Http\Controllers\Panel\Shop\ShippingSettingsController;
use App\Http\Controllers\Panel\Shop\SiteAccessController;
use App\Http\Controllers\Panel\Shop\StockController;
use App\Http\Controllers\Panel\Shop\StockCountController;
use App\Http\Controllers\Panel\Shop\StockReceiptController;
use App\Http\Controllers\Panel\Shop\StockSettingsController;
use App\Http\Controllers\Panel\Shop\SupplierController;
use App\Http\Controllers\Panel\Shop\UnitController;
use App\Http\Controllers\Panel\Shop\WarehouseController;
use App\Http\Controllers\Panel\Shop\WarehouseLocationController;
use App\Services\Panel\Shop\Reports\ReportCatalog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel — Mağaza rotaları
|--------------------------------------------------------------------------
| `panel.routes.register` hook'u içinde yüklenir: /panel önekini, `panel.`
| ad önekini ve panelin tam middleware yığınını (route_permission dahil)
| otomatik miras alır. Rota adı = yetki adı.
*/

Route::prefix('shop')->name('shop.')->group(function () {

    Route::controller(DealerGroupController::class)->prefix('dealer-groups')->name('dealer-groups.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{dealerGroup}', 'update')->name('update');
        Route::delete('/{dealerGroup}', 'destroy')->name('destroy');
    });

    Route::put('customers/{user}/type', [CustomerTypeController::class, 'update'])->name('customers.type');

    Route::controller(ProductController::class)->prefix('products')->name('products.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/search', 'search')->name('search');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{product}/edit', 'edit')->name('edit');
        Route::put('/{product}', 'update')->name('update');
        Route::patch('/{product}/status', 'status')->name('status');
        Route::delete('/{product}', 'destroy')->name('destroy');
        Route::post('/{product}/images', 'storeImages')->name('images.store');
        Route::put('/{product}/images/order', 'reorderImages')->name('images.reorder');
        Route::delete('/{product}/images/{media}', 'destroyImage')->name('images.destroy');
    });

    Route::controller(CategoryController::class)->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{category}', 'update')->name('update');
        Route::patch('/{category}/status', 'status')->name('status');
        Route::delete('/{category}', 'destroy')->name('destroy');
    });

    Route::controller(BrandController::class)->prefix('brands')->name('brands.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{brand}', 'update')->name('update');
        Route::patch('/{brand}/status', 'status')->name('status');
        Route::delete('/{brand}', 'destroy')->name('destroy');
    });

    Route::controller(BankAccountController::class)->prefix('bank-accounts')->name('bank-accounts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/payment', 'payment')->name('payment');
        Route::put('/discount', 'discount')->name('discount');
        Route::put('/{bankAccount}', 'update')->name('update');
        Route::patch('/{bankAccount}/status', 'status')->name('status');
        Route::delete('/{bankAccount}', 'destroy')->name('destroy');
    });

    Route::controller(UnitController::class)->prefix('units')->name('units.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{unit}', 'update')->name('update');
        Route::patch('/{unit}/status', 'status')->name('status');
        Route::delete('/{unit}', 'destroy')->name('destroy');
    });

    Route::controller(WarehouseController::class)->prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{warehouse}', 'show')->name('show');
        Route::get('/{warehouse}/export', 'export')->name('export');
        Route::put('/{warehouse}', 'update')->name('update');
        Route::delete('/{warehouse}', 'destroy')->name('destroy');
    });

    Route::controller(WarehouseLocationController::class)->name('warehouse-locations.')->group(function () {
        Route::post('warehouses/{warehouse}/locations', 'store')->name('store');
        Route::post('warehouses/{warehouse}/locations/bulk', 'bulk')->name('bulk');
        Route::put('warehouse-locations/{location}', 'update')->name('update');
        Route::patch('warehouse-locations/{location}/status', 'status')->name('status');
        Route::delete('warehouse-locations/{location}', 'destroy')->name('destroy');
    });

    Route::controller(StockController::class)->prefix('stock')->name('stock.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/movements', 'movements')->name('movements');
        Route::post('/adjust', 'adjust')->name('adjust');
        Route::post('/transfer', 'transfer')->name('transfer');
        Route::post('/relocate', 'relocate')->name('relocate');
        Route::get('/lots', 'lots')->name('lots');
    });

    Route::controller(StockReceiptController::class)->prefix('stock-receipts')->name('stock-receipts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/products', 'products')->name('products');
        Route::post('/', 'store')->name('store');
        Route::get('/{stockReceipt}', 'show')->name('show');
    });

    Route::controller(StockCountController::class)->prefix('stock-counts')->name('stock-counts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{stockCount}', 'show')->name('show');
        Route::get('/{stockCount}/lookup', 'lookup')->name('lookup');
        Route::post('/{stockCount}/lines', 'storeLine')->name('lines.store');
        Route::put('/{stockCount}/lines/{line}', 'updateLine')->name('lines.update');
        Route::delete('/{stockCount}/lines/{line}', 'destroyLine')->name('lines.destroy');
        Route::post('/{stockCount}/approve', 'approve')->name('approve');
        Route::post('/{stockCount}/cancel', 'cancel')->name('cancel');
    });

    Route::controller(SupplierController::class)->prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{supplier}', 'update')->name('update');
        Route::patch('/{supplier}/status', 'status')->name('status');
        Route::delete('/{supplier}', 'destroy')->name('destroy');
    });

    Route::controller(OrderController::class)->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/pick-list', 'pickList')->name('pick-list');
        Route::get('/{order}', 'show')->name('show');
        Route::patch('/{order}/status', 'status')->name('status');
        Route::patch('/{order}/payment', 'payment')->name('payment');
        Route::patch('/{order}/note', 'note')->name('note');
        Route::put('/{order}/invoice', 'invoice')->name('invoice');
        Route::post('/{order}/lot', 'changeLot')->name('change-lot');
    });

    // Cari hesap: bakiye listesi, tahsilatlar, elle kayıt, ekstre.
    Route::controller(AccountLedgerController::class)->prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/collections', 'collections')->name('collections');
        Route::get('/aging', 'aging')->name('aging');
        Route::get('/aging/export', 'agingExport')->name('aging-export');
        Route::post('/transactions/{transaction}/reverse', 'reverse')->name('reverse');
        Route::post('/{account}/collections', 'collect')->name('collect');
        Route::post('/{account}/adjustments', 'adjust')->name('adjust');
        Route::get('/{account}/export', 'export')->name('export');
        Route::get('/{account}/print', 'print')->name('print');
    });

    Route::controller(ChequeController::class)->prefix('cheques')->name('cheques.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{cheque}/image', 'image')->name('image');
        Route::post('/{cheque}/status', 'status')->name('status');
    });

    Route::post('payments/{payment}/refund', [PaymentRefundController::class, 'store'])->name('payments.refund');
    Route::post('payment-refunds/{paymentRefund}/confirm', [PaymentRefundController::class, 'confirm'])->name('payments.refunds.confirm');
    Route::post('payment-refunds/{paymentRefund}/abandon', [PaymentRefundController::class, 'abandon'])->name('payments.refunds.abandon');

    Route::controller(PaymentNotificationController::class)->prefix('payment-notifications')->name('payment-notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{paymentNotification}/receipt', 'receipt')->name('receipt');
        Route::post('/{paymentNotification}/approve', 'approve')->name('approve');
        Route::post('/{paymentNotification}/reject', 'reject')->name('reject');
    });

    Route::controller(QuoteController::class)->prefix('quotes')->name('quotes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/products', 'products')->name('products');
        Route::post('/', 'store')->name('store');
        Route::get('/{quote}', 'edit')->name('edit');
        Route::put('/{quote}', 'update')->name('update');
        Route::post('/{quote}/cancel', 'cancel')->name('cancel');
        Route::delete('/{quote}', 'destroy')->name('destroy');
    });

    Route::controller(ReturnController::class)->prefix('returns')->name('returns.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{returnRequest}', 'show')->name('show');
        Route::patch('/{returnRequest}/status', 'status')->name('status');
    });

    Route::controller(ExchangeRateController::class)->prefix('exchange-rates')->name('exchange-rates.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::post('/sync', 'sync')->name('sync');
        Route::put('/settings', 'settings')->name('settings');
        Route::post('/currencies', 'store')->name('store');
    });

    Route::controller(CarrierController::class)->prefix('carriers')->name('carriers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/rates-template', 'template')->name('template');
        Route::get('/{carrier}/edit', 'edit')->name('edit');
        Route::post('/{carrier}', 'update')->name('update');
        Route::patch('/{carrier}/status', 'status')->name('status');
        Route::delete('/{carrier}', 'destroy')->name('destroy');
        Route::put('/{carrier}/rates', 'updateRates')->name('rates.update');
        Route::post('/{carrier}/rates/import', 'importRates')->name('rates.import');
    });

    // Sözleşmeler (Tanımlamalar altında listelenir).
    Route::controller(ContractController::class)->prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{contract}/edit', 'edit')->name('edit');
        Route::put('/{contract}', 'update')->name('update');
        Route::patch('/{contract}/status', 'status')->name('status');
        Route::delete('/{contract}', 'destroy')->name('destroy');
    });

    Route::controller(CampaignController::class)->prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{campaign}', 'edit')->name('edit');
        Route::put('/{campaign}', 'update')->name('update');
        Route::patch('/{campaign}/status', 'status')->name('status');
        Route::delete('/{campaign}', 'destroy')->name('destroy');
    });

    // Raporlar: her rapor ayrı rota (= ayrı yetki); Excel'i ayrı yetki.
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');

        foreach (ReportCatalog::keys() as $key) {
            Route::get("/{$key}", 'show')->defaults('report', $key)->name($key);
            Route::get("/{$key}/export", 'export')->defaults('report', $key)->name("{$key}.export");
        }
    });

    Route::controller(LedgerSettingsController::class)->prefix('ledger-settings')->name('ledger-settings.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::controller(StockSettingsController::class)->prefix('stock-settings')->name('stock-settings.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::controller(SalesSettingsController::class)->prefix('sales-settings')->name('sales-settings.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::controller(CorporateInfoController::class)->prefix('corporate')->name('corporate.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::post('/etbis', 'uploadEtbis')->name('etbis.upload');
        Route::delete('/etbis', 'destroyEtbis')->name('etbis.destroy');
    });

    Route::controller(ShippingSettingsController::class)->prefix('shipping-settings')->name('shipping-settings.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::controller(SiteAccessController::class)->prefix('site-access')->name('site-access.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::controller(ExportController::class)->prefix('export')->name('export.')->group(function () {
        Route::get('/products', 'products')->name('products');
    });

    Route::controller(ImportController::class)->prefix('import')->name('import.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/template', 'template')->name('template');
        Route::post('/', 'store')->name('store');
    });
});
