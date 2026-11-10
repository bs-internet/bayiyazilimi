<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\Shop\DashboardService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Panel başlangıç ekranı: e-ticaret özeti. Kartlar, kullanıcının ilgili
     * listeye erişim yetkisi varsa doldurulur (yoksa null gönderilir).
     */
    public function index(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();

        $can = [
            'orders' => $user->can('panel.shop.orders.index'),
            'quotes' => $user->can('panel.shop.quotes.index'),
            'returns' => $user->can('panel.shop.returns.index'),
            'products' => $user->can('panel.shop.products.index'),
            'members' => $user->can('panel.members.index'),
            'activity' => $user->can('panel.tools.activity'),
        ];

        return Inertia::render('panel/dashboard', [
            'currency' => Money::baseCode(),
            'can' => $can,
            'periodDays' => DashboardService::PERIOD_DAYS,
            'kpis' => $can['orders'] ? $dashboard->orderKpis() : null,
            'newMembers' => $can['members'] ? $dashboard->newMembers() : null,
            'dailyRevenue' => $can['orders'] ? $dashboard->dailyRevenue() : [],
            'ordersByStatus' => $can['orders'] ? $dashboard->ordersByStatus() : [],
            'recentOrders' => $can['orders'] ? $dashboard->recentOrders() : [],
            'topProducts' => $can['orders'] ? $dashboard->topProducts() : [],
            'pending' => $dashboard->pendingTasks($can),
            'activity' => $can['activity'] ? $dashboard->recentActivity() : [],
        ]);
    }
}
