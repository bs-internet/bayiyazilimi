<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\ReturnStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\OrderService;
use App\Services\Shop\QuickOrderService;
use App\Services\Shop\StockService;
use Database\Seeders\ShopRoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Havale / EFT yalnızca aktif banka hesabı varken sunulur.
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    $this->withoutVite();
    Notification::fake();

    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->warehouse = Warehouse::query()->where('code', 'DEPO')->first();
    $this->city = City::query()->where('name', 'Bursa')->first();
    $this->district = District::query()->where('city_id', $this->city->id)->where('name', 'Nilüfer')->first();

    $this->product = Product::create([
        'sku' => 'HZ-100', 'name' => 'Hızlı Ürün', 'slug' => 'hizli-urun',
        'price' => 100, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, $this->warehouse, 20, StockMovementType::In);

    $this->quoteOnly = Product::create([
        'sku' => 'TK-200', 'name' => 'Teklif Ürünü', 'slug' => 'teklif-urunu',
        'price' => 0, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Quote, 'status' => Status::Active,
    ]);
});

function memberCompletedOrder(User $member, Product $product, int $qty = 3, ?string $accountId = null): Order
{
    $order = Order::create([
        'number' => 'S'.random_int(100000, 999999), 'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid, 'payment_method' => 'bank_transfer',
        'user_id' => $member->id, 'account_id' => $accountId ?? $member->account_id,
        'customer_name' => $member->name, 'email' => $member->email, 'phone' => '0555',
        'billing_address' => ['name' => $member->name], 'shipping_address' => ['name' => $member->name],
        'currency_code' => 'TRY', 'grand_total' => 120 * $qty, 'shipped_at' => now()->subDay(),
        'warehouse_id' => Warehouse::query()->where('code', 'DEPO')->value('id'),
    ]);

    $order->items()->create([
        'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
        'quantity' => $qty, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 20 * $qty, 'line_total' => 120 * $qty,
    ]);

    return $order;
}

function addressPayload(array $extra = []): array
{
    return array_merge([
        'title' => 'Merkez', 'type' => 'shipping', 'name' => 'Ali Veli', 'phone' => '05551112233',
        'city_id' => City::query()->where('name', 'Bursa')->value('id'),
        'district_id' => District::query()->where('name', 'Nilüfer')->value('id'),
        'address' => 'Demirci Mah. No:1', 'postal_code' => '16110',
    ], $extra);
}

it('manages member addresses and keeps them account scoped', function () {
    $this->actingAs($this->member)->post(route('app.shop.addresses.store'), addressPayload())->assertRedirect();
    $this->actingAs($this->member)->post(route('app.shop.addresses.store'), addressPayload(['title' => 'Depo', 'type' => 'shipping']))->assertRedirect();

    $first = CustomerAddress::query()->where('title', 'Merkez')->first();
    $second = CustomerAddress::query()->where('title', 'Depo')->first();

    expect($first->account_id)->toBe($this->member->account_id)
        ->and($first->is_default_shipping)->toBeTrue()
        ->and($second->is_default_shipping)->toBeFalse();

    $this->actingAs($this->member)->patch(route('app.shop.addresses.default', $second->id), ['kind' => 'shipping'])->assertRedirect();
    expect($second->fresh()->is_default_shipping)->toBeTrue()
        ->and($first->fresh()->is_default_shipping)->toBeFalse();

    $this->actingAs($this->member)->put(route('app.shop.addresses.update', $first->id), addressPayload(['title' => 'Merkez Ofis', 'is_default_billing' => '1']))->assertRedirect();
    expect($first->fresh()->title)->toBe('Merkez Ofis');

    $this->actingAs($this->member)->get(route('app.shop.addresses.index'))
        ->assertInertia(fn (Assert $page) => $page->component('app/shop/addresses/index')->has('addresses', 2)->has('cities'));

    // Başka hesabın adresine erişilemez.
    $other = Account::query()->create(['code' => 'OTHERACC1', 'title' => 'Diğer']);
    $foreign = new CustomerAddress(addressPayload(['title' => 'Yabancı']));
    $foreign->account_id = $other->id;
    $foreign->save();

    $this->actingAs($this->member)->delete(route('app.shop.addresses.destroy', $foreign->id))->assertNotFound();
    $this->actingAs($this->member)->delete(route('app.shop.addresses.destroy', $first->id))->assertRedirect();

    expect(CustomerAddress::query()->withoutGlobalScopes()->whereKey($foreign->id)->exists())->toBeTrue()
        ->and(CustomerAddress::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and($second->fresh()->is_default_shipping)->toBeTrue()
        // Teslimat adresi fatura varsayılanı olamaz.
        ->and($second->fresh()->is_default_billing)->toBeFalse();
});

it('keeps shipping and billing addresses strictly separate', function () {
    // Teslimat adresi + "fatura adresi olarak da kaydet" → iki ayrı kayıt.
    $this->actingAs($this->member)->post(route('app.shop.addresses.store'), addressPayload([
        'company' => 'ACME', 'tax_office' => 'Nilüfer', 'tax_number' => '123', 'also_as_other' => '1',
    ]))->assertRedirect();

    $shipping = CustomerAddress::query()->where('type', 'shipping')->sole();
    $billing = CustomerAddress::query()->where('type', 'billing')->sole();

    expect($shipping->is_default_shipping)->toBeTrue()
        ->and($shipping->is_default_billing)->toBeFalse()
        ->and($shipping->company)->toBeNull()
        ->and($shipping->tax_number)->toBeNull()
        ->and($billing->is_default_billing)->toBeTrue()
        ->and($billing->is_default_shipping)->toBeFalse()
        ->and($billing->address)->toBe($shipping->address);

    // Fatura adresi kurumsal bilgileri taşır; ikinci fatura adresi varsayılan olmaz.
    $this->actingAs($this->member)->post(route('app.shop.addresses.store'), addressPayload([
        'title' => 'Muhasebe', 'type' => 'billing', 'company' => 'ACME Ltd.', 'tax_office' => 'Nilüfer', 'tax_number' => '1234567890',
    ]))->assertRedirect();

    $corporate = CustomerAddress::query()->where('title', 'Muhasebe')->sole();
    expect($corporate->company)->toBe('ACME Ltd.')
        ->and($corporate->is_default_billing)->toBeFalse();

    // Varsayılan yapma türüne göre: yalnızca fatura varsayılanı değişir.
    $this->actingAs($this->member)->patch(route('app.shop.addresses.default', $corporate->id), ['kind' => 'shipping'])->assertRedirect();
    expect($corporate->fresh()->is_default_billing)->toBeTrue()
        ->and($corporate->fresh()->is_default_shipping)->toBeFalse()
        ->and($billing->fresh()->is_default_billing)->toBeFalse()
        ->and($shipping->fresh()->is_default_shipping)->toBeTrue();

    // "both" artık geçersiz.
    $this->actingAs($this->member)->post(route('app.shop.addresses.store'), addressPayload(['type' => 'both']))
        ->assertSessionHasErrors('type');

    // Varsayılan fatura adresi silinince sıradaki fatura adresi varsayılan olur (teslimat etkilenmez).
    $this->actingAs($this->member)->delete(route('app.shop.addresses.destroy', $corporate->id))->assertRedirect();
    expect($billing->fresh()->is_default_billing)->toBeTrue();
});

it('defaults customer address type to shipping', function () {
    $id = (string) Str::uuid7();
    DB::table('customer_addresses')->insert([
        'id' => $id, 'account_id' => $this->member->account_id, 'title' => 'Yeni', 'name' => 'X', 'address' => 'Y',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(DB::table('customer_addresses')->where('id', $id)->value('type'))->toBe('shipping');
});

it('validates district belongs to the selected city', function () {
    $otherDistrict = District::query()->where('city_id', '!=', $this->city->id)->value('id');

    $this->actingAs($this->member)
        ->post(route('app.shop.addresses.store'), addressPayload(['district_id' => $otherDistrict]))
        ->assertSessionHasErrors('district_id');
});

it('lists districts of a city for checkout selects', function () {
    $this->getJson(route('locations.districts', $this->city->id))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Nilüfer']);
});

it('offers saved addresses at checkout and saves a new checkout address', function () {
    $this->actingAs($this->member)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 1]);

    $this->actingAs($this->member)->get(route('app.shop.checkout.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/checkout/index')
            ->where('canSaveAddress', true)
            ->has('cities')
            ->has('savedAddresses', 0));

    $this->actingAs($this->member)->post(route('app.shop.checkout.store'), [
        'email' => 'user@user.com', 'phone' => '05551112233',
        'billing' => ['type' => 'individual', 'name' => 'Ali Veli', 'address' => 'Yeni adres 5', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => '1', 'payment_method' => 'bank_transfer', 'agreement' => '1', 'save_address' => '1',
    ])->assertRedirect();

    // Aynı adres olsa da teslimat ve fatura için iki ayrı kayıt açılır.
    $saved = CustomerAddress::query()->where('account_id', $this->member->account_id)->get()->keyBy(fn ($a) => $a->type->value);
    expect($saved)->toHaveCount(2)
        ->and($saved['billing']->address)->toBe('Yeni adres 5')
        ->and($saved['shipping']->address)->toBe('Yeni adres 5')
        ->and($saved['billing']->city_id)->toBe($this->city->id)
        ->and($saved['shipping']->district_id)->toBe($this->district->id);
});

it('lists member payments with pending and paid transfer rows', function () {
    $transfer = memberCompletedOrder($this->member, $this->product);
    $transfer->update(['payment_status' => PaymentStatus::Pending, 'status' => OrderStatus::Pending]);

    $card = memberCompletedOrder($this->member, $this->product);
    $card->update(['payment_method' => 'bank_transfer', 'payment_status' => PaymentStatus::Pending]);
    app(OrderService::class)->markPaid($card->fresh());

    $this->actingAs($this->member)->get(route('app.shop.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/payments/index')
            ->has('payments', 2)
            ->where('payments', fn ($rows) => collect($rows)->contains('awaiting_transfer', true)
                && collect($rows)->contains('status', 'paid')));
});

it('runs the return flow from member request to admin refund with restock', function () {
    $order = memberCompletedOrder($this->member, $this->product, 3);
    $item = $order->items()->first();
    $stockBefore = $this->product->fresh()->stock;

    $this->actingAs($this->member)->get(route('app.shop.returns.create', $order->id))
        ->assertInertia(fn (Assert $page) => $page->component('app/shop/returns/create')->where('order.items.0.returnable', 3));

    // Sipariş adedinden fazlası istenemez.
    $this->actingAs($this->member)
        ->post(route('app.shop.returns.store', $order->id), ['reason' => 'damaged', 'items' => [$item->id => 5]])
        ->assertRedirect();
    expect(ReturnRequest::count())->toBe(0);

    $this->actingAs($this->member)
        ->post(route('app.shop.returns.store', $order->id), ['reason' => 'damaged', 'customer_note' => 'Kırık', 'items' => [$item->id => 2]])
        ->assertRedirect();

    $return = ReturnRequest::query()->firstOrFail();
    expect($return->status)->toBe(ReturnStatus::Pending)
        ->and($return->items()->first()->quantity)->toBe(2);

    $this->actingAs($this->member)->get(route('app.shop.returns.show', $return->id))->assertOk();
    $this->actingAs($this->member)->get(route('app.shop.returns.index'))
        ->assertInertia(fn (Assert $page) => $page->has('returns.data', 1));

    // Kullanıcı değişince AuthenticateSession eski oturumu düşürür; oturumu sıfırla.
    $this->flushSession();
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();

    $this->actingAs($admin)->get(route('panel.shop.returns.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/returns/index')->has('returns.data', 1));
    $this->actingAs($admin)->get(route('panel.shop.returns.show', $return->id))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/returns/show')->where('returnRequest.suggested_refund', '240.00'));

    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'approved'])->assertRedirect();

    // Ürün teslim alınmadan iade tamamlanamaz.
    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'refunded', 'refund_amount' => '240'])->assertRedirect();
    expect($return->fresh()->status)->toBe(ReturnStatus::Approved);

    // İade tutarı iade edilen ürünlerin sipariş tutarını aşamaz.
    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'received', 'refund_amount' => '241'])->assertRedirect();
    expect($return->fresh()->status)->toBe(ReturnStatus::Approved);

    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'received', 'restock' => '1'])->assertRedirect();
    expect($return->fresh()->restocked)->toBeTrue();

    // Stoğa alınmış talep reddedilemez (aynı ürün yeniden iade edilip stoğa ikinci kez girerdi).
    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'rejected'])->assertRedirect();
    expect($return->fresh()->status)->toBe(ReturnStatus::Received);

    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), [
        'status' => 'refunded', 'refund_amount' => '240', 'restock' => '1', 'mark_refunded' => '1', 'admin_note' => 'İade edildi',
    ])->assertRedirect();

    $return->refresh();
    expect($return->status)->toBe(ReturnStatus::Refunded)
        ->and($return->refund_amount)->toEqualDecimal('240.0')
        ->and($return->restocked)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe($stockBefore + 2)
        // Kısmi iade: siparişin tamamı iade edilmediği için ödeme durumu "İade Edildi" olmaz.
        ->and($order->fresh()->payment_status)->not->toBe(PaymentStatus::Refunded);

    // Sonuçlanmış talep değiştirilemez.
    $this->actingAs($admin)->patch(route('panel.shop.returns.status', $return->id), ['status' => 'pending'])->assertRedirect();
    expect($return->fresh()->status)->toBe(ReturnStatus::Refunded);
});

it('rejects return requests outside the return window or for foreign orders', function () {
    $old = memberCompletedOrder($this->member, $this->product);
    $old->update(['shipped_at' => now()->subDays(40)]);

    $this->actingAs($this->member)->get(route('app.shop.returns.create', $old->id))->assertRedirect(route('app.shop.returns.index'));

    $other = Account::query()->create(['code' => 'OTHERACC2', 'title' => 'Diğer']);
    $foreign = memberCompletedOrder($this->member, $this->product, 1, $other->id);

    $this->actingAs($this->member)->get(route('app.shop.returns.create', $foreign->id))->assertNotFound();
});

it('finds products and adds them to the cart on the order page; reorders past orders', function () {
    $this->actingAs($this->member)->getJson(route('app.shop.products.search', ['q' => 'H']))->assertExactJson([]);
    $this->actingAs($this->member)->getJson(route('app.shop.products.search', ['q' => 'HZ-1']))
        ->assertOk()->assertJsonPath('0.sku', 'HZ-100')->assertJsonPath('0.purchasable', true)->assertJsonPath('0.quote_only', false);

    // Kategori / marka süzgeci arama metni olmadan da listeler (alt kategoriler dahil).
    $parent = Category::query()->create(['name' => 'Hızlı Ana', 'slug' => 'hizli-ana', 'status' => 'active']);
    $child = Category::query()->create(['name' => 'Hızlı Alt', 'slug' => 'hizli-alt', 'parent_id' => $parent->id, 'status' => 'active']);
    $this->product->update(['category_id' => $child->id]);

    $this->actingAs($this->member)->getJson(route('app.shop.products.search', ['category_id' => $parent->id]))
        ->assertOk()->assertJsonFragment(['sku' => 'HZ-100']);

    $this->actingAs($this->member)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertRedirect();
    expect(Basket::cart()->quantityOf($this->product))->toBe(2);

    // Teklif listesine bayi panelinden eklenir.
    $this->actingAs($this->member)->post(route('app.shop.quote-list.add'), ['product_id' => $this->product->id, 'quantity' => 1])
        ->assertRedirect()->assertSessionHas('toast');
    expect(Basket::quote()->quantityOf($this->product))->toBe(1);

    $order = memberCompletedOrder($this->member, $this->product, 4);
    Basket::cart()->clear();

    $this->actingAs($this->member)->post(route('app.shop.orders.reorder', $order->id))->assertRedirect(route('app.shop.cart.index'));
    expect(Basket::cart()->quantityOf($this->product))->toBe(4);

    $this->actingAs($this->member)->get(route('app.shop.cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/cart/index')
            ->has('lines', 1)
            ->has('categories')
            ->where('frequentProducts.0.sku', 'HZ-100'));
});

it('renders the e-commerce dashboard with stats, orders and quotes', function () {
    memberCompletedOrder($this->member, $this->product, 2);

    $this->actingAs($this->member)->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/dashboard')
            ->where('stats.total_orders', 1)
            ->has('recentOrders', 1)
            ->where('orderUrl', route('app.shop.cart.index'))
            ->has('recentQuotes')
            ->where('dealer.group', 'Standart Bayi')
            ->where('dealer.rate', fn ($rate) => $rate === '10.00'));
});

it('shows the new member menu items', function () {
    $this->actingAs($this->member)->get(route('app.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups) {
            $items = collect($groups)->flatMap(fn ($g) => $g['items']);

            // "Hesabım" sol menüde değil, üst çubuktaki kullanıcı menüsündedir.
            return ! $items->pluck('key')->contains('app.account')
                && $items->pluck('key')->contains('app.shop.payments.index')
                && $items->pluck('key')->contains('app.shop.returns.index');
        }));
});

it('lets sales role manage returns', function () {
    $this->seed(ShopRoleSeeder::class);
    $sales = User::factory()->create(['user_type' => UserType::Admin, 'status' => UserStatus::Active]);
    $sales->assignRole('Satış Sorumlusu');

    $this->actingAs($sales)->get(route('panel.shop.returns.index'))->assertOk();
});

it('applies the cart rules on quick order and reorder; refuses foreign orders', function () {
    $quick = app(QuickOrderService::class);
    $cart = Basket::cart();

    // En az adet yukarı yuvarlanır, en çok adet ve stok aşılamaz.
    $this->product->update(['min_qty' => 3, 'max_qty' => 10]);
    expect($quick->add($this->product->refresh(), 1))->toBeNull()
        ->and($cart->quantityOf($this->product))->toBe(3)
        ->and($quick->add($this->product, 8))->toBe(__('En fazla :max_qty adet sipariş verilebilir.', ['max_qty' => 10]))
        ->and($cart->quantityOf($this->product))->toBe(3);

    $this->product->update(['max_qty' => null]);
    $this->product->refresh();
    expect($quick->add($this->product, 50))->toBe(__('Yeterli stok yok (mevcut: :stock).', ['stock' => $this->product->stock]));

    // Teklifle satılan ve pasif ürün sepete girmez.
    $this->product->update(['sale_mode' => SaleMode::Quote]);
    expect($quick->add($this->product->refresh(), 1))->toBe(__('Yalnızca teklif ile satılıyor.'));
    $this->product->update(['sale_mode' => SaleMode::Cart, 'status' => 'passive']);
    expect($quick->add($this->product->refresh(), 1))->toBe(__('Ürün satışta değil.'));
    $this->product->update(['status' => 'active']);

    // Silinmiş ürün tekrar siparişte "eklenemedi" olarak raporlanır.
    $cart->clear();
    $order = memberCompletedOrder($this->member, $this->product, 4);
    $this->product->delete();
    $result = $quick->reorder($order->refresh());
    expect($result['added'])->toBe([])
        ->and($result['failed'][0]['sku'])->toBe('HZ-100');

    $this->actingAs($this->member)->post(route('app.shop.orders.reorder', $order->id))
        ->assertRedirect(route('app.shop.cart.index'))
        ->assertSessionHas('toast.type', 'error');

    // Başka hesabın siparişi tekrarlanamaz.
    $other = Account::query()->create(['code' => 'OTHERACC3', 'title' => 'Diğer']);
    $this->product->restore();
    $foreign = memberCompletedOrder($this->member, $this->product, 1, $other->id);
    $this->actingAs($this->member)->post(route('app.shop.orders.reorder', $foreign->id))->assertNotFound();
});
