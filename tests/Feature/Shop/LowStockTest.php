<?php

declare(strict_types=1);

use App\Enums\Shop\RateSource;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Enums\UserType;
use App\Mail\Shop\LowStockMail;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Shop\LowStockNotification;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();

    $currency = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2, 'thousands_separator' => '.',
        'decimal_separator' => ',', 'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
        'status' => Status::Active, 'sort_order' => 1,
    ]);

    Warehouse::query()->update(['is_default' => false]);
    $this->warehouse = Warehouse::query()->create(['code' => 'KRT', 'name' => 'Kritik Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);

    $this->product = Product::query()->create([
        'sku' => 'KRT-1', 'name' => 'Kritik Ürün', 'slug' => 'kritik-urun', 'price' => 100,
        'currency_id' => $currency->id, 'tax_rate' => 20, 'sale_mode' => 'cart', 'unit' => 'Adet', 'status' => Status::Active,
    ])->fresh(); // DB varsayılanları (track_stock = true) modele gelsin.

    $this->superAdmin = User::factory()->admin()->create();

    // Stok Durumu yetkisi olan yönetici bildirim alır; olmayan almaz.
    Permission::query()->firstOrCreate(['name' => 'panel.shop.stock.index', 'guard_name' => 'web']);
    $this->stockKeeper = User::factory()->create(['user_type' => UserType::Admin]);
    $this->stockKeeper->givePermissionTo('panel.shop.stock.index');
    $this->otherAdmin = User::factory()->create(['user_type' => UserType::Admin]);
    $this->member = User::factory()->create();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->stock = app(StockService::class);
});

test('notifies authorised admins once when stock drops to the threshold', function () {
    $this->product->update(['min_stock' => 3]);
    $this->stock->change($this->product, $this->warehouse, 10, StockMovementType::In);

    // 10 → 4: eşiğin üstünde.
    $this->stock->change($this->product, $this->warehouse, -6, StockMovementType::Out);
    Notification::assertNothingSent();

    // 4 → 3: eşiğe indi; bir kez bildirilir.
    $this->stock->change($this->product, $this->warehouse, -1, StockMovementType::Out);
    Notification::assertSentTo([$this->superAdmin, $this->stockKeeper], LowStockNotification::class);
    Notification::assertNotSentTo([$this->otherAdmin, $this->member], LowStockNotification::class);
    Notification::assertSentToTimes($this->stockKeeper, LowStockNotification::class, 1);
    expect($this->product->fresh()->low_stock_notified_at)->not->toBeNull();

    // Eşiğin altında düşmeye devam etmek yeni bildirim üretmez.
    $this->stock->change($this->product, $this->warehouse, -2, StockMovementType::Out);
    Notification::assertSentToTimes($this->stockKeeper, LowStockNotification::class, 1);

    // Eşiğin üstüne çıkınca işaret sıfırlanır, sonraki düşüşte yeniden bildirilir.
    $this->stock->change($this->product, $this->warehouse, 9, StockMovementType::In);
    expect($this->product->fresh()->low_stock_notified_at)->toBeNull();

    $this->stock->change($this->product, $this->warehouse, -8, StockMovementType::Out);
    Notification::assertSentToTimes($this->stockKeeper, LowStockNotification::class, 2);
});

test('uses the general threshold when the product has none and respects the notify switch', function () {
    ShopSettings::save(['low_stock_threshold' => '2']);
    $this->stock->change($this->product, $this->warehouse, 5, StockMovementType::In);

    $this->stock->change($this->product, $this->warehouse, -2, StockMovementType::Out);
    Notification::assertNothingSent();

    $this->stock->change($this->product, $this->warehouse, -1, StockMovementType::Out);
    Notification::assertSentToTimes($this->stockKeeper, LowStockNotification::class, 1);

    // Bildirim kapalıyken işaret yine tutulur ama gönderim olmaz.
    ShopSettings::save(['low_stock_notify' => false]);
    $this->stock->change($this->product, $this->warehouse, 10, StockMovementType::In);
    $this->stock->change($this->product, $this->warehouse, -10, StockMovementType::Out);
    Notification::assertSentToTimes($this->stockKeeper, LowStockNotification::class, 1);
});

test('rising stock that stays under the threshold, untracked and passive products do not notify', function () {
    $this->product->update(['min_stock' => 10]);

    // İlk giriş / içe aktarım: artış, eşiğin altında kalsa da bildirim yok.
    $this->stock->change($this->product, $this->warehouse, 4, StockMovementType::In);
    Notification::assertNothingSent();

    $this->product->update(['track_stock' => false]);
    $this->stock->change($this->product, $this->warehouse, -1, StockMovementType::Out);
    Notification::assertNothingSent();

    $this->product->update(['track_stock' => true, 'status' => Status::Passive]);
    $this->stock->change($this->product, $this->warehouse, -1, StockMovementType::Out);
    Notification::assertNothingSent();
});

test('the in-app notification carries the product and the stock screen filters critical products', function () {
    $this->product->update(['min_stock' => 3]);
    $this->stock->change($this->product, $this->warehouse, 5, StockMovementType::In);
    $this->stock->change($this->product, $this->warehouse, -3, StockMovementType::Out);

    Notification::assertSentTo($this->stockKeeper, LowStockNotification::class, function (LowStockNotification $n) {
        $data = $n->toArray($this->stockKeeper);

        return $data['type'] === 'shop.low_stock'
            && $data['product_id'] === $this->product->id
            && str_contains($data['title'], 'KRT-1');
    });

    $this->actingAs($this->superAdmin)
        ->get(route('panel.shop.stock.index', ['stock' => 'low']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.low_stock', true)
            ->where('products.data.0.threshold', 3));

    $this->product->update(['min_stock' => 1]);

    $this->actingAs($this->superAdmin)
        ->get(route('panel.shop.stock.index', ['stock' => 'low']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('product form exposes the per-product and general critical stock', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('panel.shop.products.edit', $this->product->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.min_stock', null)
            ->where('defaults.low_stock_threshold', 5));
});

test('the low stock mail renders', function () {
    $this->product->update(['min_stock' => 3, 'stock' => 0, 'allow_backorder' => true]);

    $html = (new LowStockMail($this->product->fresh()))->render();

    expect($html)->toContain('Stok tükendi')->toContain('KRT-1')->toContain('ön siparişe açık');
});
