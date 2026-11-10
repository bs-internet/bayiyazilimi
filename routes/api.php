<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountsController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\StockController;
use App\Support\Api\ApiAbilities;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1
|--------------------------------------------------------------------------
|
| /api/v1/... — `api` ara katman grubu (bootstrap/app.php). Kimlik: Sanctum
| kişisel erişim anahtarı (Authorization: Bearer {anahtar}), Ayarlar → API
| Anahtarları ekranından oluşturulur. Anahtar etkin bir yöneticiye aittir ve
| API o kullanıcı olarak çalışır (etkinlik kayıtları onun adına düşer).
| Her uç nokta bir yetki ister (`abilities:{yetki}`, bkz. ApiAbilities).
|
| Eklentiler kendi uç noktalarını `api.routes.register` kancasında ekler;
| rotaları bu grubun içinde tanımlanır: /api/v1 önekini, `api.v1.` ad
| önekini ve kimlik / etkin yönetici / istek sınırı ara katmanlarını alır.
|
*/

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'api_user', 'throttle:api'])->group(function () {
    Route::controller(ProductsController::class)->prefix('products')->name('products.')->group(function () {
        Route::get('/', 'index')->middleware('abilities:'.ApiAbilities::PRODUCTS_READ)->name('index');
        Route::get('/{product:sku}', 'show')->middleware('abilities:'.ApiAbilities::PRODUCTS_READ)->name('show');
        Route::patch('/{product:sku}', 'update')->middleware('abilities:'.ApiAbilities::PRODUCTS_WRITE)->name('update');
    });

    Route::put('stock', [StockController::class, 'update'])->middleware('abilities:'.ApiAbilities::STOCK_WRITE)->name('stock.update');

    Route::controller(OrdersController::class)->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', 'index')->middleware('abilities:'.ApiAbilities::ORDERS_READ)->name('index');
        Route::get('/{order:number}', 'show')->middleware('abilities:'.ApiAbilities::ORDERS_READ)->name('show');
        Route::patch('/{order:number}/status', 'status')->middleware('abilities:'.ApiAbilities::ORDERS_WRITE)->name('status');
        Route::put('/{order:number}/invoice', 'invoice')->middleware('abilities:'.ApiAbilities::ORDERS_WRITE)->name('invoice');
    });

    Route::controller(AccountsController::class)->prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', 'index')->middleware('abilities:'.ApiAbilities::ACCOUNTS_READ)->name('index');
        Route::get('/{account:code}', 'show')->middleware('abilities:'.ApiAbilities::ACCOUNTS_READ)->name('show');
        Route::post('/{account:code}/transactions', 'storeTransaction')->middleware('abilities:'.ApiAbilities::LEDGER_WRITE)->name('transactions.store');
    });

    hooks()->do('api.routes.register');
});
