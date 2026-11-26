<?php

declare(strict_types=1);

use App\Http\Controllers\Shop\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ödeme sağlayıcısı geri dönüşleri (web grubu, herkese açık)
|--------------------------------------------------------------------------
| CSRF dışı (bootstrap/app.php) ve site erişim kapısı dışı (SiteAccessGate).
*/

Route::controller(PaymentCallbackController::class)->prefix('odeme')->name('shop.payment.')->group(function () {
    Route::match(['get', 'post'], 'bildirim/{gateway}', 'notify')->middleware('throttle:120,1')->name('notify');
    Route::match(['get', 'post'], 'donus/{gateway}', 'return')->middleware('throttle:60,1')->name('return');
    // Kart ödeme sayfası (imzalı, süreli): sağlayıcının güvenli ödeme sayfası (iFrame / yönlendirme).
    // Kart ekranı mağaza sayfası olarak açılabilir: vitrin kendi ortak verisini (header / footer)
    // `shop.payment.page_middleware` filtresiyle ekler.
    Route::get('kart/{reference}', 'pay')->middleware(['signed', 'throttle:30,1', ...hooks()->apply('shop.payment.page_middleware', [])])->name('pay');
    // Mağazanın kendi kart ekranından "Ödeme Yap" (CardFormGateway; imzalı, CSRF korumalı).
    Route::post('kart/{reference}', 'charge')->middleware(['signed', 'throttle:10,1'])->name('charge');
    // Taksit tablosu / BIN sorgusu (ödeme ekranı, bilgi amaçlı).
    Route::get('taksit/{gateway}', 'installments')->middleware('throttle:30,1')->name('installments');
});
