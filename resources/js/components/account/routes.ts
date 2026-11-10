import appAddresses from '@/routes/app/shop/addresses';
import appOrders from '@/routes/app/shop/orders';
import appPayments from '@/routes/app/shop/payments';
import appQuoteList from '@/routes/app/shop/quote-list';
import appQuotes from '@/routes/app/shop/quotes';
import appReturns from '@/routes/app/shop/returns';

/**
 * Üye alanı ekranlarının (sipariş, teklif, adres, ödeme, iade) rotaları.
 * Çekirdekte bayi paneli `/app` (app.shop.*); vitrin modülü kendi "Hesabım"
 * alanını (`store`, shop.account.*) registerAccountRoutes() ile ekler. Rota adı
 * sonekleri iki alanda aynıdır; içerik bileşenleri Wayfinder yardımcılarını
 * buradan alır.
 */
export type AccountArea = 'app' | 'store';

const APP_ROUTES = {
    orders: appOrders,
    quotes: appQuotes,
    addresses: appAddresses,
    payments: appPayments,
    returns: appReturns,
    quoteList: appQuoteList,
};

export type AccountRoutes = typeof APP_ROUTES;

const ROUTES: Partial<Record<AccountArea, AccountRoutes>> = {
    app: APP_ROUTES,
};

export function registerAccountRoutes(
    area: AccountArea,
    routes: AccountRoutes,
): void {
    ROUTES[area] = routes;
}

export function accountRoutes(area: AccountArea): AccountRoutes {
    const routes = ROUTES[area];

    if (!routes) {
        throw new Error(`Üye alanı rotaları tanımlı değil: ${area}`);
    }

    return routes;
}
