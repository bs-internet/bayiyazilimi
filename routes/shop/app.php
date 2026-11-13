<?php

declare(strict_types=1);

use App\Http\Controllers\App\Shop\AddressController;
use App\Http\Controllers\App\Shop\CartController;
use App\Http\Controllers\App\Shop\CheckoutController;
use App\Http\Controllers\App\Shop\ContractController;
use App\Http\Controllers\App\Shop\OrderController;
use App\Http\Controllers\App\Shop\PaymentController;
use App\Http\Controllers\App\Shop\QuickOrderController;
use App\Http\Controllers\App\Shop\QuoteController;
use App\Http\Controllers\App\Shop\QuoteListController;
use App\Http\Controllers\App\Shop\ReturnController;
use App\Http\Controllers\App\Shop\SavedCartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Üye alanı — e-ticaret ekranları
|--------------------------------------------------------------------------
| `app.routes.register` hook'u içinde yüklenir (bind_account dahil).
| Siparişlerim, Tekliflerim, Adreslerim, Ödemelerim, İadelerim, hızlı sipariş.
*/

Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');

    // Tekliflerim: görüntüle, onayla / revize iste / reddet / geri çek, onaylıyı öde.
    Route::controller(QuoteController::class)->prefix('quotes')->name('quotes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{quote}', 'show')->name('show');
        Route::post('/{quote}/approve', 'approve')->name('approve');
        Route::post('/{quote}/revision', 'revision')->name('revision');
        Route::post('/{quote}/reject', 'reject')->name('reject');
        Route::post('/{quote}/withdraw', 'withdraw')->name('withdraw');
        Route::get('/{quote}/checkout', 'checkout')->name('checkout');
        Route::post('/{quote}/checkout', 'placeOrder')->middleware('throttle:10,1')->name('place-order');
    });

    Route::controller(AddressController::class)->prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{address}', 'update')->name('update');
        Route::patch('/{address}/default', 'makeDefault')->name('default');
        Route::delete('/{address}', 'destroy')->name('destroy');
    });

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payment-notifications', [PaymentController::class, 'notify'])->middleware('throttle:10,1')->name('payment-notifications.store');
    // Cari tahsilat: bakiyeye kartla ödeme (kart modülü kuruluysa).
    Route::post('balance-payments', [PaymentController::class, 'payBalance'])->middleware('throttle:10,1')->name('balance-payments.store');

    Route::controller(ReturnController::class)->prefix('returns')->name('returns.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create/{order}', 'create')->name('create');
        Route::post('/{order}', 'store')->name('store');
        Route::get('/{returnRequest}', 'show')->name('show');
    });

    Route::controller(QuickOrderController::class)->group(function () {
        Route::get('products/search', 'search')->middleware('throttle:60,1')->name('products.search');
        Route::post('cart/add', 'add')->name('cart.add');
        Route::post('cart/import', 'import')->middleware('throttle:20,1')->name('cart.import');
        Route::get('cart/import-template', 'template')->name('cart.import-template');
    });
});

/*
| Bayi alışverişi: Sepetim, Teklif Listem, Kayıtlı Sepetler ve ödeme.
| Sepet / teklif listesi / ödeme controller'ları vitrinle ortaktır
| (ResolvesBasketArea alanı rota adından çözer); oturum sepeti de ortaktır.
*/
Route::name('shop.')->group(function () {
    // Sipariş ver (eski Sepetim): ürün arama + sepet + ödeme / teklif.
    Route::permanentRedirect('sepet', 'siparis-ver');

    Route::controller(CartController::class)->prefix('siparis-ver')->name('cart.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('guncelle', 'update')->name('update');
        Route::post('sil/{product}', 'remove')->name('remove');
        Route::post('teklif-listesine-tasi', 'moveToQuote')->name('to-quote');
    });

    Route::controller(QuoteListController::class)->prefix('teklif-listesi')->name('quote-list.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('ekle', 'add')->name('add');
        Route::post('guncelle', 'update')->name('update');
        Route::post('sil/{product}', 'remove')->name('remove');
        Route::post('gonder', 'submit')->middleware('throttle:10,1')->name('submit');
    });

    Route::controller(SavedCartController::class)->prefix('kayitli-sepetler')->name('saved-carts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{savedCart}', 'show')->name('show');
        Route::put('/{savedCart}', 'update')->name('update');
        Route::delete('/{savedCart}', 'destroy')->name('destroy');
        Route::put('/{savedCart}/kalemler', 'items')->name('items');
        Route::get('/{savedCart}/siparis', 'checkout')->name('checkout');
        Route::post('/{savedCart}/siparis', 'placeOrder')->middleware('throttle:10,1')->name('place-order');
    });

    Route::controller(CheckoutController::class)->prefix('odeme')->name('checkout.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    });

    // Sözleşmeler: aktif sözleşmelerin okunduğu yer (Tanımlamalar → Sözleşmeler).
    Route::controller(ContractController::class)->prefix('sozlesmeler')->name('contracts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{slug}', 'show')->name('show');
    });
});
