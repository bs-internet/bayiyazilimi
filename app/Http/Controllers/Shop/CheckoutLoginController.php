<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ödeme formundaki "Giriş yapın": giriş sayfasına gider, girişten sonra ödeme
 * sayfasına döner (Fortify girişten sonra `url.intended`'a yönlendirir). Sepet
 * oturumda kalır.
 */
class CheckoutLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $back = url()->previous();

        // Yalnız kendi sitemize dönülür.
        if (parse_url($back, PHP_URL_HOST) !== $request->getHost()) {
            $back = route('home');
        }

        redirect()->setIntendedUrl($back);

        return redirect()->route('login');
    }
}
