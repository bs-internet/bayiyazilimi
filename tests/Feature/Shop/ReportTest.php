<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignUsage;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\Panel\Shop\Reports\ReportCatalog;
use App\Support\Pagination;
use App\Support\Permissions\PermissionDependencies;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function reportOrder(array $attributes = [], array $item = []): Order
{
    $order = Order::query()->create(array_merge([
        'number' => 'R'.random_int(100000, 999999),
        'status' => OrderStatus::Processing,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer',
        'customer_name' => 'Ayşe',
        'email' => 'ayse@example.com',
        'phone' => '5550000000',
        'billing_address' => ['name' => 'Ayşe'],
        'shipping_address' => ['name' => 'Ayşe'],
        'currency_code' => 'TRY',
        'exchange_rate' => 1,
        'grand_total' => 100,
    ], $attributes));

    $order->items()->create(array_merge([
        'sku' => 'RP-1', 'name' => 'Rapor Ürünü', 'quantity' => 2,
        'unit_price' => 50, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $order->grand_total,
    ], $item));

    return $order;
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();

    Currency::query()->updateOrCreate(['code' => 'EUR'], [
        'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',',
        'exchange_rate' => 40, 'status' => Status::Active, 'sort_order' => 2,
    ]);
});

test('reports link redirects to the first report', function () {
    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.index'))
        ->assertRedirect(route('panel.shop.reports.sales'));
});

test('every report renders for an admin', function (string $key) {
    reportOrder();

    $this->actingAs($this->admin)
        ->get(route("panel.shop.reports.{$key}", ['preset' => 'this_year']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/shop/reports/show')
            ->where('report.key', $key)
            ->has('reports', count(ReportCatalog::keys()))
            ->has('summary.kpis')
            ->has('columns')
            ->has('table.data')
            ->where('exportUrl', fn ($url) => str_contains((string) $url, "/reports/{$key}/export")));
})->with(ReportCatalog::keys());

test('sales report counts revenue in TL and skips cancelled orders', function () {
    reportOrder(['grand_total' => 1000]);
    reportOrder(['grand_total' => 10, 'currency_code' => 'EUR', 'exchange_rate' => 40]); // 400 TL
    reportOrder(['grand_total' => 5000, 'status' => OrderStatus::Cancelled]);
    reportOrder(['grand_total' => 700, 'payment_status' => PaymentStatus::Refunded]);
    $old = reportOrder(['grand_total' => 600]);
    $old->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.sales'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', '30d')
            ->where('summary.kpis.0.label', 'Ciro')
            ->where('summary.kpis.0.value', '1400.00')
            ->where('summary.kpis.0.change', 133.3)
            ->where('summary.kpis.1.value', 2)
            ->where('summary.kpis.2.value', '700.00')
            ->has('summary.chart.data', 30)
            ->has('table.data', min(30, Pagination::PER_PAGE))
            ->where('table.total', 30));
});

test('custom date range and customer type filter the sales report', function () {
    $dealer = Account::factory()->create(['type' => CustomerType::Dealer]);
    reportOrder(['grand_total' => 300, 'account_id' => $dealer->id]);
    reportOrder(['grand_total' => 200]);

    $today = now()->toDateString();

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.sales', ['preset' => 'custom', 'from' => $today, 'to' => $today, 'customer_type' => 'dealer']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'custom')
            ->where('filters.customer_type', 'dealer')
            ->where('summary.kpis.0.value', '300.00')
            ->has('summary.chart.data', 1));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.sales', ['customer_type' => 'guest']))
        ->assertInertia(fn (Assert $page) => $page->where('summary.kpis.0.value', '200.00'));
});

test('product sales report groups by product and category', function () {
    reportOrder(['grand_total' => 300], ['sku' => 'A-1', 'name' => 'Ürün A', 'quantity' => 3, 'line_total' => 300]);
    reportOrder(['grand_total' => 100], ['sku' => 'A-1', 'name' => 'Ürün A', 'quantity' => 1, 'line_total' => 100]);
    reportOrder(['grand_total' => 100], ['sku' => 'B-1', 'name' => 'Ürün B', 'quantity' => 1, 'line_total' => 100]);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.products'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.kpis.0.value', 5)
            ->where('table.data.0.sku', 'A-1')
            ->where('table.data.0.quantity', 4)
            ->where('table.data.0.orders', 2)
            ->where('table.data.0.revenue', '400.00')
            ->where('table.data.0.share', 80));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.products', ['group_by' => 'category']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.name', 'Kategorisiz')
            ->where('table.data.0.products', 2));
});

test('customer report separates new and returning customers', function () {
    $old = reportOrder(['email' => 'eski@example.com']);
    $old->forceFill(['created_at' => now()->subDays(90)])->save();
    reportOrder(['email' => 'eski@example.com', 'grand_total' => 500]);
    reportOrder(['email' => 'yeni@example.com', 'grand_total' => 200]);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.customers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.kpis.0.value', 2)
            ->where('summary.kpis.1.value', 1)
            ->where('summary.kpis.2.value', 1)
            ->where('table.data.0.email', 'eski@example.com')
            ->where('table.data.0.type', 'Misafir'));
});

test('stock report lists out of stock and idle products', function () {
    $try = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',', 'exchange_rate' => 1,
        'status' => Status::Active, 'sort_order' => 1,
    ]);
    $make = fn (string $sku, int $stock, float $price = 10) => Product::create([
        'sku' => $sku, 'name' => "Stok {$sku}", 'slug' => strtolower($sku),
        'price' => $price, 'currency_id' => $try->id, 'stock' => $stock,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);

    $sold = $make('ST-SOLD', 10);
    $make('ST-IDLE', 3, 100);
    $make('ST-OUT', 0);
    reportOrder([], ['product_id' => $sold->id, 'sku' => 'ST-SOLD']);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.stock', ['stock_state' => 'out']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.total', 1)
            ->where('table.data.0.sku', 'ST-OUT'));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.stock', ['stock_state' => 'idle']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.total', 1)
            ->where('table.data.0.sku', 'ST-IDLE')
            ->where('table.data.0.value', '300.00'));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.stock', ['stock_state' => 'low', 'threshold' => 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.threshold', '5')
            ->where('table.data.0.sku', 'ST-IDLE'));
});

test('quote report computes conversion rate', function () {
    $base = ['customer_name' => 'Firma', 'email' => 'f@example.com', 'currency_code' => 'TRY', 'exchange_rate' => 1, 'grand_total' => 100];
    $order = reportOrder();
    Quote::query()->create([...$base, 'number' => 'T1', 'status' => QuoteStatus::Approved, 'responded_at' => now()->addHours(2), 'order_id' => $order->id]);
    Quote::query()->create([...$base, 'number' => 'T2', 'status' => QuoteStatus::Approved, 'responded_at' => now()->addHours(4)]);
    Quote::query()->create([...$base, 'number' => 'T3', 'status' => QuoteStatus::Rejected]);
    Quote::query()->create([...$base, 'number' => 'T4', 'status' => QuoteStatus::Pending]);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.quotes'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.kpis.0.value', 4)
            ->where('summary.kpis.1.value', 2)
            ->where('summary.kpis.2.value', 1)
            ->where('summary.kpis.2.hint', 'Dönüşüm oranı %25,0')
            ->where('summary.kpis.3.value', '200.00')
            ->has('summary.kpis', 5)
            ->where('table.total', 4));
});

test('campaign report counts each applied campaign from usage records', function () {
    $summer = Campaign::create(['name' => 'Yaz İndirimi', 'action' => 'percent', 'action_settings' => ['rate' => 10], 'product_scope' => 'all']);
    $shipping = Campaign::create(['name' => 'Ücretsiz Kargo', 'action' => 'shipping', 'action_settings' => [], 'product_scope' => 'all', 'shipping_mode' => 'free']);
    $use = function (Order $order, Campaign $campaign, string $value): void {
        CampaignUsage::query()->create(['campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'order_id' => $order->id, 'email' => $order->email, 'value' => $value]);
    };

    $use(reportOrder(['campaign_name' => 'Yaz İndirimi', 'discount_total' => 50, 'grand_total' => 450]), $summer, '50');
    // Birlikte uygulanan iki kampanya: ikisi de kendi satırında sayılır.
    $both = reportOrder(['campaign_name' => 'Yaz İndirimi + Ücretsiz Kargo', 'discount_total' => 50, 'grand_total' => 250]);
    $use($both, $summer, '50');
    $use($both, $shipping, '30');
    reportOrder(['grand_total' => 100]);

    // Silinen kampanya kayıttaki adıyla raporda kalır.
    $shipping->delete();

    $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.campaigns'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.kpis.0.value', 2)
            ->where('summary.kpis.1.value', '100.00')
            ->where('table.data.0.name', 'Yaz İndirimi')
            ->where('table.data.0.orders', 2)
            ->where('table.data.0.benefit', '100.00')
            ->where('table.data.0.average', '350.00')
            ->where('table.data.1.name', 'Ücretsiz Kargo')
            ->where('table.data.1.benefit', '30.00')
            ->where('table.data.1.action', null));
});

test('report exports to excel', function () {
    reportOrder();

    $response = $this->actingAs($this->admin)
        ->get(route('panel.shop.reports.sales.export', ['preset' => '7d']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('Content-Disposition'))->toContain('satis-raporu-');
});

test('members cannot open reports', function () {
    // Panel üyeleri kendi alanlarına yönlendirir.
    $this->actingAs(User::factory()->member()->create())
        ->get(route('panel.shop.reports.sales'))
        ->assertRedirect();

    $this->actingAs(User::factory()->member()->create())
        ->get(route('panel.shop.reports.sales.export'))
        ->assertRedirect();
});

test('export permission depends on the report permission', function () {
    foreach (['panel.shop.reports.index', 'panel.shop.reports.stock', 'panel.shop.reports.stock.export'] as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $map = app(PermissionDependencies::class)->map();

    expect($map['panel.shop.reports.stock.export'] ?? [])->toContain('panel.shop.reports.stock')
        ->and($map['panel.shop.reports.stock'] ?? [])->toContain('panel.shop.reports.index');
});
