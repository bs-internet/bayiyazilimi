<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shop\AddressType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\ReturnReason;
use App\Enums\Status;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Shop\AddressService;
use App\Services\Shop\OrderService;
use App\Services\Shop\PriceCalculator;
use App\Services\Shop\ReturnService;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Örnek üye (user@user.com) için demo verisi: kayıtlı adres, tamamlanmış bir
 * sipariş ve bir iade talebi — üye alanı ve panel ekranları boş görünmesin.
 * Tekrar çalıştırılabilir (varsa atlar).
 */
class MemberDemoSeeder extends Seeder
{
    public function run(AddressService $addresses, PriceCalculator $prices, ReturnService $returns): void
    {
        $user = User::query()->where('email', 'user@user.com')->with('account')->first();

        if ($user === null || $user->account === null) {
            return;
        }

        $account = $user->account;
        $city = City::query()->where('name', 'Bursa')->first();
        $district = $city ? District::query()->where('city_id', $city->id)->where('name', 'Nilüfer')->first() : null;

        if (! CustomerAddress::query()->where('account_id', $account->id)->exists()) {
            // Fatura adresi + aynı bilgilerle ayrı bir teslimat adresi.
            $addresses->save($account, [
                'title' => 'İş yeri',
                'type' => AddressType::Billing,
                'name' => $user->name,
                'company' => 'Örnek Ticaret Ltd. Şti.',
                'tax_office' => 'Nilüfer',
                'tax_number' => '1234567890',
                'phone' => '05551112233',
                'city_id' => $city?->id,
                'district_id' => $district?->id,
                'address' => 'Demirci Mah. Örnek Sok. No:1',
                'postal_code' => '16110',
                'is_default_billing' => true,
            ], alsoAsOther: true);
        }

        if (Order::query()->where('account_id', $account->id)->exists()) {
            return;
        }

        $products = Product::query()
            ->where('status', Status::Active->value)
            ->where('price', '>', 1)
            ->whereHas('images')
            ->with('currency')
            ->orderByDesc('sales_count')
            ->limit(2)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $address = CustomerAddress::query()->where('account_id', $account->id)->with(['city', 'district'])->first();
        $billing = $address?->toOrderAddress() ?? ['name' => $user->name, 'address' => '-', 'city' => 'Bursa', 'district' => 'Nilüfer'];

        $order = Order::create([
            'number' => OrderService::nextNumber('S', 'orders'),
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'bank_transfer',
            'user_id' => $user->id,
            'account_id' => $account->id,
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => '05551112233',
            'billing_address' => $billing,
            'shipping_address' => $billing,
            'currency_code' => 'TRY',
            'exchange_rate' => 1,
            'paid_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(3),
            // Örnek veride stok düşülmez.
            'stock_deducted' => false,
        ]);

        $subtotal = Money::round(0);
        $tax = Money::round(0);

        foreach ($products as $i => $product) {
            $qty = $i + 1;
            $net = $prices->net($product, $order->currency());
            $line = Money::line($net, $qty, $product->tax_rate);
            $subtotal = Money::add($subtotal, $line['net']);
            $tax = Money::add($tax, $line['tax']);

            $order->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $qty,
                'unit_price' => $net,
                'tax_rate' => $product->tax_rate,
                'tax_amount' => $line['tax'],
                'line_total' => $line['gross'],
            ]);
        }

        $order->forceFill([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'grand_total' => Money::add($subtotal, $tax),
        ])->save();

        $order->histories()->create(['status' => OrderStatus::Completed, 'note' => 'Örnek sipariş (demo).']);

        if (! ReturnRequest::query()->where('order_id', $order->id)->exists()) {
            $first = $order->items()->first();

            $returns->request($order, $user, [
                'reason' => ReturnReason::Damaged->value,
                'customer_note' => 'Kutu hasarlı geldi (örnek talep).',
                'items' => [$first->id => 1],
            ]);
        }
    }
}
