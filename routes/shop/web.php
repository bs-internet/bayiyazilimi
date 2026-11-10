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
    Route::get('kart/{reference}', 'pay')->middleware(['signed', 'throttle:30,1'])->name('pay');
    // Taksit tablosu / BIN sorgusu (ödeme ekranı, bilgi amaçlı).
    Route::get('taksit/{gateway}', 'installments')->middleware('throttle:30,1')->name('installments');
});
