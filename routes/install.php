<?php

declare(strict_types=1);

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

// Kurulum sihirbazı. `installer` ara katman grubu (bootstrap/app.php) veritabanı
// gerektirmez; kurulu sistemde EnsureInstalled bu rotaları 404 yapar.
Route::controller(InstallController::class)->prefix('kurulum')->name('install.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/veritabani', 'database')->middleware('throttle:20,1')->name('database');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
});
