<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountsController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\WebhooksController;
use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\Api\EnsureIdempotency;
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
| Yazma isteklerinde `Idempotency-Key` başlığı aynı isteğin iki kez
| işlenmesini önler (EnsureIdempotency). Belge: docs/api/openapi.yaml.
|
| Eklentiler kendi uç noktalarını `api.routes.register` kancasında ekler;
| rotaları bu grubun içinde tanımlanır: /api/v1 önekini, `api.v1.` ad
| önekini ve kimlik / etkin yönetici / istek sınırı ara katmanlarını alır.
|
*/

// Makine okunur API tanımı (kimlik istemez).
Route::get('v1/openapi.yaml', [MetaController::class, 'openapi'])->name('api.v1.openapi');

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'api_user', 'throttle:api', EnsureIdempotency::class])->group(function () {
    $can = fn (string $ability): string => 'abilities:'.$ability;

    Route::get('/', [MetaController::class, 'index'])->name('index');

    Route::controller(ProductsController::class)->prefix('products')->name('products.')->group(function () use ($can) {
        Route::get('/', 'index')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('index');
        Route::post('/', 'store')->middleware($can(ApiAbilities::PRODUCTS_WRITE))->name('store');
        Route::get('/{product:sku}', 'show')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('show');
        Route::patch('/{product:sku}', 'update')->middleware($can(ApiAbilities::PRODUCTS_WRITE))->name('update');
        Route::delete('/{product:sku}', 'destroy')->middleware($can(ApiAbilities::PRODUCTS_WRITE))->name('destroy');
    });
    Route::put('prices', [ProductsController::class, 'prices'])->middleware($can(ApiAbilities::PRODUCTS_WRITE))->name('prices.update');

    Route::get('stock', [StockController::class, 'index'])->middleware($can(ApiAbilities::PRODUCTS_READ))->name('stock.index');
    Route::put('stock', [StockController::class, 'update'])->middleware($can(ApiAbilities::STOCK_WRITE))->name('stock.update');

    Route::controller(CatalogController::class)->group(function () use ($can) {
        Route::get('categories', 'categories')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('categories.index');
        Route::post('categories', 'storeCategory')->middleware($can(ApiAbilities::CATALOG_WRITE))->name('categories.store');
        Route::patch('categories/{category:slug}', 'updateCategory')->middleware($can(ApiAbilities::CATALOG_WRITE))->name('categories.update');
        Route::get('brands', 'brands')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('brands.index');
        Route::post('brands', 'storeBrand')->middleware($can(ApiAbilities::CATALOG_WRITE))->name('brands.store');
        Route::patch('brands/{brand:slug}', 'updateBrand')->middleware($can(ApiAbilities::CATALOG_WRITE))->name('brands.update');
        Route::get('warehouses', 'warehouses')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('warehouses.index');
        Route::get('currencies', 'currencies')->middleware($can(ApiAbilities::PRODUCTS_READ))->name('currencies.index');
        Route::get('carriers', 'carriers')->middleware($can(ApiAbilities::ORDERS_READ))->name('carriers.index');
        Route::get('dealer-groups', 'dealerGroups')->middleware($can(ApiAbilities::ACCOUNTS_READ))->name('dealer-groups.index');
    });

    Route::controller(OrdersController::class)->prefix('orders')->name('orders.')->group(function () use ($can) {
        Route::get('/', 'index')->middleware($can(ApiAbilities::ORDERS_READ))->name('index');
        Route::post('/', 'store')->middleware($can(ApiAbilities::ORDERS_WRITE))->name('store');
        Route::get('/{order:number}', 'show')->middleware($can(ApiAbilities::ORDERS_READ))->name('show');
        Route::patch('/{order:number}/status', 'status')->middleware($can(ApiAbilities::ORDERS_WRITE))->name('status');
        Route::put('/{order:number}/invoice', 'invoice')->middleware($can(ApiAbilities::ORDERS_WRITE))->name('invoice');
    });

    Route::controller(AccountsController::class)->prefix('accounts')->name('accounts.')->group(function () use ($can) {
        Route::get('/', 'index')->middleware($can(ApiAbilities::ACCOUNTS_READ))->name('index');
        Route::post('/', 'store')->middleware($can(ApiAbilities::ACCOUNTS_WRITE))->name('store');
        Route::get('/{account:code}', 'show')->middleware($can(ApiAbilities::ACCOUNTS_READ))->name('show');
        Route::patch('/{account:code}', 'update')->middleware($can(ApiAbilities::ACCOUNTS_WRITE))->name('update');
        Route::get('/{account:code}/transactions', 'transactions')->middleware($can(ApiAbilities::ACCOUNTS_READ))->name('transactions.index');
        Route::post('/{account:code}/transactions', 'storeTransaction')->middleware($can(ApiAbilities::LEDGER_WRITE))->name('transactions.store');
    });

    Route::controller(WebhooksController::class)->prefix('webhooks')->name('webhooks.')->middleware($can(ApiAbilities::WEBHOOKS_MANAGE))->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/events', 'events')->name('events');
        Route::post('/', 'store')->name('store');
        Route::patch('/{webhook}', 'update')->whereUuid('webhook')->name('update');
        Route::delete('/{webhook}', 'destroy')->whereUuid('webhook')->name('destroy');
        Route::post('/{webhook}/ping', 'ping')->whereUuid('webhook')->name('ping');
        Route::get('/{webhook}/deliveries', 'deliveries')->whereUuid('webhook')->name('deliveries');
    });

    hooks()->do('api.routes.register');
});

/*
| MCP (Model Context Protocol) sunucusu: /api/mcp. Yapay zekâ istemcileri
| (Claude, Cursor …) aynı API anahtarıyla bağlanır; araçlar REST API uç
| noktalarını çağırır, yetkiler aynıdır (bkz. App\Support\Mcp\McpTools).
*/
Route::match(['get', 'post', 'delete'], 'mcp', McpController::class)
    ->middleware(['auth:sanctum', 'api_user', 'throttle:api'])
    ->name('mcp');
